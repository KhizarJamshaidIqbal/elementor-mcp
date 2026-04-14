#!/usr/bin/env node

/**
 * Companion CLI for MCP Tools for Elementor.
 *
 * Supports two modes:
 * - endpoint: stdio bridge to the plugin's MCP HTTP endpoint
 * - rest:     standalone stdio MCP server using WordPress REST API fallback
 *
 * @package Elementor_MCP
 * @since   1.4.4
 */

import { appendFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { dirname, resolve } from 'node:path';

const CLI_VERSION = '0.1.0';
const args = parseArgs(process.argv.slice(2));
const MODE = String(args.mode || process.env.ELEMENTOR_MCP_MODE || 'endpoint').toLowerCase();
const WP_URL = String(args['wp-url'] || args.wpUrl || process.env.WP_URL || '').replace(/\/+$/, '');
const WP_USERNAME = String(args['wp-username'] || args.wpUsername || process.env.WP_USERNAME || process.env.WP_APP_USER || '');
const WP_APP_PASSWORD = String(args['wp-app-password'] || args.wpAppPassword || process.env.WP_APP_PASSWORD || '');
const USER_AGENT =
	String(args['user-agent'] || args.userAgent || process.env.ELEMENTOR_MCP_USER_AGENT || '') ||
	'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36';
const PROTOCOL_VERSION = String(args['protocol-version'] || args.protocolVersion || process.env.MCP_PROTOCOL_VERSION || '2024-11-05');
const DEFAULT_LOG_FILE = resolve(process.cwd(), '.elementor-mcp-cli.log');
const LOG_FILE = String(args['log-file'] || args.logFile || process.env.MCP_LOG_FILE || DEFAULT_LOG_FILE);
const MCP_REST_PATH = '/mcp/elementor-mcp-server';

if (args.help || args.h) {
	showHelp();
	process.exit(0);
}

if (!WP_URL || !WP_USERNAME || !WP_APP_PASSWORD) {
	process.stderr.write('Missing required configuration. Provide WP_URL, WP_USERNAME, and WP_APP_PASSWORD.\n');
	process.exit(1);
}

const wpUrl = new URL(WP_URL);
const isLocalDev =
	/\.(test|local|localhost|dev|invalid)$/.test(wpUrl.hostname) ||
	wpUrl.hostname === 'localhost' ||
	wpUrl.hostname === '127.0.0.1';
const authHeader = `Basic ${Buffer.from(`${WP_USERNAME}:${WP_APP_PASSWORD}`).toString('base64')}`;

let sessionId = null;
let usePrettyPermalinks = null;
let stdinBuffer = Buffer.alloc(0);
let messageQueue = Promise.resolve();

const REST_TOOLS = [
	{
		name: 'get_page',
		description: 'Fetch one Elementor-built page or post by post_id using the WordPress REST API fallback.',
		inputSchema: {
			type: 'object',
			properties: {
				post_id: { type: 'integer', description: 'WordPress post ID.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Optional content type hint.' },
			},
			required: ['post_id'],
		},
	},
	{
		name: 'get_page_by_slug',
		description: 'Resolve an Elementor-built page or post by slug using the WordPress REST API fallback.',
		inputSchema: {
			type: 'object',
			properties: {
				slug: { type: 'string', description: 'Page or post slug.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Optional content type filter.' },
			},
			required: ['slug'],
		},
	},
	{
		name: 'get_page_id_by_slug',
		description: 'Resolve an Elementor-built page or post by slug and return a compact identifier payload.',
		inputSchema: {
			type: 'object',
			properties: {
				slug: { type: 'string', description: 'Page or post slug.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Optional content type filter.' },
			},
			required: ['slug'],
		},
	},
	{
		name: 'download_page_to_file',
		description: 'Download one Elementor-built page payload to a local JSON file.',
		inputSchema: {
			type: 'object',
			properties: {
				post_id: { type: 'integer', description: 'WordPress post ID.' },
				slug: { type: 'string', description: 'Optional slug alternative to post_id.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Optional content type filter.' },
				file_path: { type: 'string', description: 'Local output JSON file path.' },
			},
			required: ['file_path'],
		},
	},
	{
		name: 'update_page_from_file',
		description: 'Read a local JSON file and update an existing page or post through the WordPress REST API fallback.',
		inputSchema: {
			type: 'object',
			properties: {
				post_id: { type: 'integer', description: 'WordPress post ID. Falls back to the JSON file payload if omitted.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Optional content type hint.' },
				file_path: { type: 'string', description: 'Local JSON file path.' },
			},
			required: ['file_path'],
		},
	},
	{
		name: 'create_page',
		description: 'Create a page or post with optional Elementor meta through the WordPress REST API fallback.',
		inputSchema: {
			type: 'object',
			properties: {
				title: { type: 'string', description: 'Post title.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Content type. Default: page.' },
				status: { type: 'string', enum: ['draft', 'publish', 'pending', 'private', 'future'], description: 'Post status.' },
				slug: { type: 'string', description: 'Optional slug.' },
				excerpt: { type: 'string', description: 'Optional excerpt.' },
				template: { type: 'string', description: 'Optional page template slug.' },
				featured_media: { type: 'integer', description: 'Optional featured image attachment ID.' },
				elementor_data: { type: 'array', items: { type: 'object' }, description: 'Optional Elementor element tree.' },
				page_settings: { type: 'object', description: 'Optional Elementor page settings.' },
			},
			required: ['title'],
		},
	},
	{
		name: 'update_page',
		description: 'Update page/post fields and optional Elementor meta through the WordPress REST API fallback.',
		inputSchema: {
			type: 'object',
			properties: {
				post_id: { type: 'integer', description: 'WordPress post ID.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Optional content type hint.' },
				title: { type: 'string', description: 'Optional title update.' },
				slug: { type: 'string', description: 'Optional slug update.' },
				status: { type: 'string', enum: ['draft', 'publish', 'pending', 'private', 'future'], description: 'Optional status update.' },
				excerpt: { type: 'string', description: 'Optional excerpt update.' },
				template: { type: 'string', description: 'Optional page template update.' },
				featured_media: { type: 'integer', description: 'Optional featured image attachment ID.' },
				elementor_data: { type: 'array', items: { type: 'object' }, description: 'Optional Elementor element tree update.' },
				page_settings: { type: 'object', description: 'Optional Elementor page settings update.' },
			},
			required: ['post_id'],
		},
	},
	{
		name: 'duplicate_page',
		description: 'Duplicate an Elementor-built page or post through the WordPress REST API fallback.',
		inputSchema: {
			type: 'object',
			properties: {
				post_id: { type: 'integer', description: 'WordPress post ID.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Optional content type hint.' },
				status: { type: 'string', enum: ['draft', 'publish', 'pending', 'private'], description: 'Status for the duplicate. Default: draft.' },
				title_suffix: { type: 'string', description: 'Optional suffix appended to the duplicate title.' },
			},
			required: ['post_id'],
		},
	},
	{
		name: 'delete_page',
		description: 'Delete a page or post using the WordPress REST API fallback.',
		inputSchema: {
			type: 'object',
			properties: {
				post_id: { type: 'integer', description: 'WordPress post ID.' },
				post_type: { type: 'string', enum: ['page', 'post'], description: 'Optional content type hint.' },
				force: { type: 'boolean', description: 'Force delete instead of trashing. Default: true.' },
			},
			required: ['post_id'],
		},
	},
];

function showHelp() {
	process.stdout.write(`elementor-mcp-cli v${CLI_VERSION}

Usage:
  node src/index.mjs --mode=endpoint --wp-url=http://example.test --wp-username=admin --wp-app-password="xxxx xxxx"
  node src/index.mjs --mode=rest --wp-url=http://example.test --wp-username=admin --wp-app-password="xxxx xxxx"

Environment variables:
  WP_URL, WP_USERNAME (or WP_APP_USER), WP_APP_PASSWORD
  ELEMENTOR_MCP_MODE, MCP_PROTOCOL_VERSION, MCP_LOG_FILE
`);
}

function parseArgs(argv) {
	const parsed = {};

	for (let index = 0; index < argv.length; index += 1) {
		const token = argv[index];
		if (!token.startsWith('--')) {
			continue;
		}

		const body = token.slice(2);
		const equalsIndex = body.indexOf('=');

		if (equalsIndex >= 0) {
			parsed[body.slice(0, equalsIndex)] = body.slice(equalsIndex + 1);
			continue;
		}

		const next = argv[index + 1];
		if (next && !next.startsWith('--')) {
			parsed[body] = next;
			index += 1;
			continue;
		}

		parsed[body] = true;
	}

	return parsed;
}

function log(message) {
	const line = `[${new Date().toISOString()}] ${message}`;
	try {
		mkdirSync(dirname(LOG_FILE), { recursive: true });
		appendFileSync(LOG_FILE, `${line}\n`);
	} catch {}
}

function writeFramed(payload) {
	const json = typeof payload === 'string' ? payload : JSON.stringify(payload);
	const body = Buffer.from(json, 'utf8');
	const header = Buffer.from(`Content-Length: ${body.length}\r\n\r\n`, 'utf8');
	process.stdout.write(Buffer.concat([header, body]));
}

function sendSuccess(id, result) {
	writeFramed({ jsonrpc: '2.0', id, result });
}

function sendError(id, code, message, data = undefined) {
	const payload = {
		jsonrpc: '2.0',
		id,
		error: { code, message },
	};

	if (data !== undefined) {
		payload.error.data = data;
	}

	writeFramed(payload);
}

function toolError(code, message, data = undefined) {
	const error = new Error(message);
	error.toolCode = code;
	error.toolData = data;
	return error;
}

function isObject(value) {
	return !!value && typeof value === 'object' && !Array.isArray(value);
}

function normalizeRichText(value) {
	if (typeof value === 'string') {
		return value;
	}

	if (isObject(value)) {
		if (typeof value.raw === 'string') {
			return value.raw;
		}
		if (typeof value.rendered === 'string') {
			return value.rendered;
		}
	}

	return '';
}

function parsePossibleJson(value, fallback) {
	if (Array.isArray(value) || isObject(value)) {
		return value;
	}

	if (typeof value === 'string' && value.trim() !== '') {
		try {
			return JSON.parse(value);
		} catch {}
	}

	return fallback;
}

function getSupportedTypes(postType = '') {
	if (!postType) {
		return ['page', 'post'];
	}

	if (!['page', 'post'].includes(postType)) {
		throw toolError('invalid_post_type', 'Only "page" and "post" are supported.');
	}

	return [postType];
}

function getRouteForType(postType) {
	if (postType === 'page') {
		return '/wp/v2/pages';
	}

	if (postType === 'post') {
		return '/wp/v2/posts';
	}

	throw toolError('invalid_post_type', 'Only "page" and "post" are supported.');
}

async function doHttpRequest(options, payload = '') {
	return new Promise((resolve, reject) => {
		const isHttps = wpUrl.protocol === 'https:';
		const send = isHttps ? httpsRequest : httpRequest;

		if (isHttps && isLocalDev) {
			options.rejectUnauthorized = false;
		}

		const req = send(options, (response) => {
			let body = '';
			response.on('data', (chunk) => {
				body += chunk;
			});
			response.on('end', () => {
				resolve({
					statusCode: response.statusCode || 0,
					headers: response.headers,
					body,
				});
			});
		});

		req.on('error', reject);
		req.setTimeout(30000, () => req.destroy(new Error('Request timeout')));

		if (payload) {
			req.write(payload);
		}

		req.end();
	});
}

async function detectPermalinks() {
	try {
		const isHttps = wpUrl.protocol === 'https:';
		const options = {
			hostname: wpUrl.hostname,
			port: wpUrl.port || (isHttps ? 443 : 80),
			path: '/wp-json/',
			method: 'HEAD',
			headers: { Accept: 'application/json' },
		};
		const response = await doHttpRequest(options);
		return response.statusCode !== 404;
	} catch {
		return false;
	}
}

async function getEndpointPath(routePath) {
	if (usePrettyPermalinks === null) {
		usePrettyPermalinks = await detectPermalinks();
		log(`Permalink detection: ${usePrettyPermalinks ? 'pretty' : 'plain'}`);
	}

	if (usePrettyPermalinks) {
		return `/wp-json${routePath}`;
	}

	return `/?rest_route=${encodeURIComponent(routePath)}`;
}

function appendQuery(path, query) {
	const entries = Object.entries(query).filter(([, value]) => value !== undefined && value !== null && value !== '');
	if (entries.length === 0) {
		return path;
	}

	const queryString = entries
		.map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(String(value))}`)
		.join('&');

	return `${path}${path.includes('?') ? '&' : '?'}${queryString}`;
}

function getRoutePathCandidates(routePath) {
	const prettyPath = `/wp-json${routePath}`;
	const plainPath = `/?rest_route=${encodeURIComponent(routePath)}`;

	if (usePrettyPermalinks === true) {
		return [prettyPath, plainPath];
	}

	if (usePrettyPermalinks === false) {
		return [plainPath, prettyPath];
	}

	return [prettyPath, plainPath];
}

async function requestJson(routePath, { method = 'GET', query = {}, body = null, headers = {} } = {}) {
	const isHttps = wpUrl.protocol === 'https:';
	const payload = body === null ? '' : JSON.stringify(body);
	let lastError = null;

	for (const candidatePath of getRoutePathCandidates(routePath)) {
		const path = appendQuery(candidatePath, query);
		const options = {
			hostname: wpUrl.hostname,
			port: wpUrl.port || (isHttps ? 443 : 80),
			path,
			method,
			headers: {
				Accept: 'application/json',
				Authorization: authHeader,
				'User-Agent': USER_AGENT,
				...headers,
			},
		};

		if (body !== null) {
			options.headers['Content-Type'] = 'application/json';
			options.headers['Content-Length'] = Buffer.byteLength(payload);
		}

		try {
			const response = await doHttpRequest(options, payload);
			let parsedBody = null;

			if (response.body.trim() !== '') {
				try {
					parsedBody = JSON.parse(response.body);
				} catch {
					parsedBody = response.body;
				}
			}

			if (response.statusCode >= 400) {
				const isMissingRoute =
					response.statusCode === 404 ||
					(
						response.statusCode === 400 &&
						(
							parsedBody?.code === 'rest_no_route' ||
							parsedBody?.code === 'rest_not_found'
						)
					);

				if (isMissingRoute) {
					lastError = toolError(
						`http_${response.statusCode}`,
						typeof parsedBody?.message === 'string' ? parsedBody.message : `HTTP ${response.statusCode}`,
						{
							status: response.statusCode,
							body: parsedBody,
							route: routePath,
							method,
							path,
						},
					);
					continue;
				}

				if (
					response.statusCode === 401 &&
					parsedBody?.code === 'rest_forbidden_context' &&
					routePath.startsWith('/wp/v2/')
				) {
					throw toolError(
						'rest_edit_context_forbidden',
						'The REST fallback could authenticate, but this user cannot access edit-context data for this post type. The fallback needs edit access so it can verify Elementor metadata. Use endpoint mode or a user that can edit this content type.',
						{
							status: response.statusCode,
							body: parsedBody,
							route: routePath,
							method,
							path,
						},
					);
				}

				throw toolError(
					`http_${response.statusCode}`,
					typeof parsedBody?.message === 'string' ? parsedBody.message : `HTTP ${response.statusCode}`,
					{
						status: response.statusCode,
						body: parsedBody,
						route: routePath,
						method,
						path,
					},
				);
			}

			usePrettyPermalinks = candidatePath.startsWith('/wp-json');
			return parsedBody;
		} catch (error) {
			lastError = error;
		}
	}

	throw lastError || toolError('request_failed', `Failed to request route "${routePath}".`);
}

function buildRestPagePayload(post, postType) {
	const meta = isObject(post.meta) ? post.meta : {};
	const elementorData = parsePossibleJson(meta._elementor_data, []);
	const pageSettings = parsePossibleJson(meta._elementor_page_settings, {});
	const elementorEnabled = meta._elementor_edit_mode === 'builder' || (Array.isArray(elementorData) && elementorData.length > 0);
	const warnings = [];

	if (!('_elementor_edit_mode' in meta) && !('_elementor_data' in meta)) {
		warnings.push('Elementor meta is not exposed through this REST response.');
	}

	return {
		post_id: post.id,
		post_type: postType,
		slug: post.slug || '',
		title: normalizeRichText(post.title),
		status: post.status || '',
		link: post.link || '',
		modified: post.modified || '',
		excerpt: normalizeRichText(post.excerpt),
		template: typeof post.template === 'string' && post.template !== '' ? post.template : 'default',
		featured_media: typeof post.featured_media === 'number' ? post.featured_media : 0,
		elementor_enabled: elementorEnabled,
		elementor_data: Array.isArray(elementorData) ? elementorData : [],
		page_settings: isObject(pageSettings) ? pageSettings : {},
		meta,
		warnings,
	};
}

function buildCompactPagePayload(post) {
	return {
		post_id: post.post_id,
		post_type: post.post_type,
		slug: post.slug,
		title: post.title,
	};
}

function ensureElementorPage(payload) {
	if (!payload.elementor_enabled) {
		throw toolError('not_elementor_page', 'This content exists, but Elementor data is not available through the REST fallback.', {
			post_id: payload.post_id,
			post_type: payload.post_type,
			warnings: payload.warnings,
		});
	}

	return payload;
}

async function fetchPostById(postId, explicitPostType = '') {
	const types = getSupportedTypes(explicitPostType);
	let lastError = null;

	for (const postType of types) {
		try {
			const post = await requestJson(`${getRouteForType(postType)}/${postId}`, {
				query: { context: 'edit' },
			});
			return buildRestPagePayload(post, postType);
		} catch (error) {
			lastError = error;
			const status = error?.toolData?.status;
			if (status === 404 || status === 400) {
				continue;
			}
			throw error;
		}
	}

	throw lastError || toolError('page_not_found', `No page or post found for ID ${postId}.`);
}

async function fetchRawPostEntityById(postId, explicitPostType = '') {
	const types = getSupportedTypes(explicitPostType);
	let lastError = null;

	for (const postType of types) {
		try {
			const post = await requestJson(`${getRouteForType(postType)}/${postId}`, {
				query: { context: 'edit' },
			});
			return { post, postType };
		} catch (error) {
			lastError = error;
			const status = error?.toolData?.status;
			if (status === 404 || status === 400) {
				continue;
			}
			throw error;
		}
	}

	throw lastError || toolError('page_not_found', `No page or post found for ID ${postId}.`);
}

async function fetchPostBySlug(slug, explicitPostType = '') {
	const normalizedSlug = String(slug || '').trim();
	if (!normalizedSlug) {
		throw toolError('missing_slug', 'The slug parameter is required.');
	}

	const types = getSupportedTypes(explicitPostType);
	const allMatches = [];

	for (const postType of types) {
		const posts = await requestJson(getRouteForType(postType), {
			query: {
				slug: normalizedSlug,
				context: 'edit',
				per_page: 20,
			},
		});

		if (!Array.isArray(posts)) {
			continue;
		}

		for (const post of posts) {
			allMatches.push(buildRestPagePayload(post, postType));
		}
	}

	if (allMatches.length === 0) {
		throw toolError('page_not_found', `No page or post found with slug "${normalizedSlug}".`);
	}

	const elementorMatches = allMatches.filter((match) => match.elementor_enabled);

	if (elementorMatches.length === 0) {
		throw toolError('not_elementor_page', 'Matching content was found, but Elementor data is not available through the REST fallback.', {
			candidates: allMatches.map(buildCompactPagePayload),
		});
	}

	if (elementorMatches.length > 1) {
		throw toolError('ambiguous_slug', 'Multiple Elementor pages matched this slug. Provide post_type or post_id.', {
			candidates: elementorMatches.map(buildCompactPagePayload),
		});
	}

	return elementorMatches[0];
}

function buildMetaPayload(argsInput, postType) {
	const meta = {};

	if (Array.isArray(argsInput.elementor_data)) {
		meta._elementor_data = JSON.stringify(argsInput.elementor_data);
		meta._elementor_edit_mode = 'builder';
		meta._elementor_template_type = `wp-${postType}`;
	}

	if (isObject(argsInput.page_settings)) {
		meta._elementor_page_settings = argsInput.page_settings;
		meta._elementor_edit_mode = 'builder';
	}

	return meta;
}

function applyCommonPostFields(argsInput, target = {}) {
	if (typeof argsInput.title === 'string') {
		target.title = argsInput.title;
	}
	if (typeof argsInput.slug === 'string') {
		target.slug = argsInput.slug;
	}
	if (typeof argsInput.status === 'string') {
		target.status = argsInput.status;
	}
	if (typeof argsInput.excerpt === 'string') {
		target.excerpt = argsInput.excerpt;
	}
	if (typeof argsInput.template === 'string') {
		target.template = argsInput.template;
	}
	if (Number.isInteger(argsInput.featured_media)) {
		target.featured_media = argsInput.featured_media;
	}
	return target;
}

function rewriteMetaCapabilityError(error, action) {
	const body = error?.toolData?.body;
	const message = typeof body?.message === 'string' ? body.message : error.message;

	if (String(message).toLowerCase().includes('meta')) {
		throw toolError(
			'rest_meta_unsupported',
			`The site rejected Elementor meta during ${action}. This REST fallback requires Elementor meta to be writable via REST. Use endpoint mode or the plugin MCP tools instead.`,
			error.toolData,
		);
	}

	throw error;
}

async function toolGetPage(argsInput = {}) {
	const postId = Number(argsInput.post_id || 0);
	if (!postId) {
		throw toolError('missing_post_id', 'The post_id parameter is required.');
	}

	return ensureElementorPage(await fetchPostById(postId, String(argsInput.post_type || '')));
}

async function toolGetPageBySlug(argsInput = {}) {
	return ensureElementorPage(await fetchPostBySlug(argsInput.slug, String(argsInput.post_type || '')));
}

async function toolGetPageIdBySlug(argsInput = {}) {
	return buildCompactPagePayload(await toolGetPageBySlug(argsInput));
}

async function toolDownloadPageToFile(argsInput = {}) {
	if (typeof argsInput.file_path !== 'string' || argsInput.file_path.trim() === '') {
		throw toolError('missing_file_path', 'The file_path parameter is required.');
	}

	let payload;
	if (Number(argsInput.post_id || 0) > 0) {
		payload = await toolGetPage(argsInput);
	} else if (typeof argsInput.slug === 'string' && argsInput.slug.trim() !== '') {
		payload = await toolGetPageBySlug(argsInput);
	} else {
		throw toolError('missing_page_reference', 'Provide either post_id or slug.');
	}

	const filePath = resolve(process.cwd(), argsInput.file_path);
	writeFileSync(filePath, JSON.stringify(payload, null, 2), 'utf8');

	return {
		post_id: payload.post_id,
		slug: payload.slug,
		file_path: filePath,
	};
}

async function toolUpdatePageFromFile(argsInput = {}) {
	if (typeof argsInput.file_path !== 'string' || argsInput.file_path.trim() === '') {
		throw toolError('missing_file_path', 'The file_path parameter is required.');
	}

	const filePath = resolve(process.cwd(), argsInput.file_path);
	const parsed = JSON.parse(readFileSync(filePath, 'utf8'));
	const postId = Number(argsInput.post_id || parsed.post_id || 0);

	if (!postId) {
		throw toolError('missing_post_id', 'Provide post_id or include post_id in the JSON file.');
	}

	return toolUpdatePage({
		post_id: postId,
		post_type: argsInput.post_type || parsed.post_type,
		title: parsed.title,
		slug: parsed.slug,
		status: parsed.status,
		excerpt: parsed.excerpt,
		template: parsed.template,
		featured_media: parsed.featured_media || parsed.featured_image?.id || undefined,
		elementor_data: parsed.elementor_data || parsed.elements,
		page_settings: parsed.page_settings,
	});
}

async function toolCreatePage(argsInput = {}) {
	if (typeof argsInput.title !== 'string' || argsInput.title.trim() === '') {
		throw toolError('missing_title', 'The title parameter is required.');
	}

	const postType = String(argsInput.post_type || 'page');
	getSupportedTypes(postType);

	const body = applyCommonPostFields(argsInput, {
		title: argsInput.title,
		status: String(argsInput.status || 'draft'),
	});
	const meta = buildMetaPayload(argsInput, postType);
	if (Object.keys(meta).length > 0) {
		body.meta = meta;
	}

	try {
		const post = await requestJson(getRouteForType(postType), {
			method: 'POST',
			query: { context: 'edit' },
			body,
		});
		return buildRestPagePayload(post, postType);
	} catch (error) {
		if (Object.keys(meta).length > 0) {
			rewriteMetaCapabilityError(error, 'page creation');
		}
		throw error;
	}
}

async function toolUpdatePage(argsInput = {}) {
	const postId = Number(argsInput.post_id || 0);
	if (!postId) {
		throw toolError('missing_post_id', 'The post_id parameter is required.');
	}

	const existing = await fetchPostById(postId, String(argsInput.post_type || ''));
	const body = applyCommonPostFields(argsInput, {});
	const meta = buildMetaPayload(argsInput, existing.post_type);

	if (Object.keys(meta).length > 0) {
		body.meta = meta;
	}

	if (Object.keys(body).length === 0) {
		throw toolError('missing_update_fields', 'Provide at least one page field or Elementor payload to update.');
	}

	try {
		const post = await requestJson(`${getRouteForType(existing.post_type)}/${postId}`, {
			method: 'POST',
			query: { context: 'edit' },
			body,
		});
		return buildRestPagePayload(post, existing.post_type);
	} catch (error) {
		if (Object.keys(meta).length > 0) {
			rewriteMetaCapabilityError(error, 'page update');
		}
		throw error;
	}
}

function buildDuplicateRestBody(sourcePost, argsInput = {}) {
	const status = typeof argsInput.status === 'string' ? argsInput.status : 'draft';
	const titleSuffix = typeof argsInput.title_suffix === 'string' ? argsInput.title_suffix.trim() : '';
	const sourceTitle = normalizeRichText(sourcePost.title);
	const title = titleSuffix ? `${sourceTitle} ${titleSuffix}` : sourceTitle;
	const meta = isObject(sourcePost.meta) ? { ...sourcePost.meta } : {};
	const commentStatus = ['open', 'closed'].includes(sourcePost.comment_status) ? sourcePost.comment_status : 'closed';
	const pingStatus = ['open', 'closed'].includes(sourcePost.ping_status) ? sourcePost.ping_status : '';

	delete meta._elementor_css;

	const body = {
		title,
		status,
		content: normalizeRichText(sourcePost.content),
		excerpt: normalizeRichText(sourcePost.excerpt),
		comment_status: commentStatus,
		parent: Number(sourcePost.parent || 0),
		password: typeof sourcePost.password === 'string' ? sourcePost.password : '',
		menu_order: Number(sourcePost.menu_order || 0),
	};

	if (pingStatus) {
		body.ping_status = pingStatus;
	}

	if (typeof sourcePost.template === 'string' && sourcePost.template !== '') {
		body.template = sourcePost.template;
	}

	if (Number.isInteger(sourcePost.featured_media) && sourcePost.featured_media > 0) {
		body.featured_media = sourcePost.featured_media;
	}

	if (Array.isArray(sourcePost.categories)) {
		body.categories = sourcePost.categories;
	}

	if (Array.isArray(sourcePost.tags)) {
		body.tags = sourcePost.tags;
	}

	if (Object.keys(meta).length > 0) {
		body.meta = meta;
	}

	return body;
}

async function toolDuplicatePage(argsInput = {}) {
	const postId = Number(argsInput.post_id || 0);
	if (!postId) {
		throw toolError('missing_post_id', 'The post_id parameter is required.');
	}

	const { post: sourcePost, postType } = await fetchRawPostEntityById(postId, String(argsInput.post_type || ''));
	const sourcePayload = ensureElementorPage(buildRestPagePayload(sourcePost, postType));
	const body = buildDuplicateRestBody(sourcePost, argsInput);

	try {
		const duplicated = await requestJson(getRouteForType(postType), {
			method: 'POST',
			query: { context: 'edit' },
			body,
		});
		const duplicatedPayload = buildRestPagePayload(duplicated, postType);

		return {
			...duplicatedPayload,
			source_post_id: sourcePayload.post_id,
			copied_taxonomies: ['categories', 'tags'].filter((key) => Array.isArray(sourcePost[key]) && sourcePost[key].length > 0),
			copied_meta_key_count: Object.keys(isObject(sourcePost.meta) ? sourcePost.meta : {}).filter((key) => key !== '_elementor_css').length,
		};
	} catch (error) {
		if (isObject(sourcePost.meta) && Object.keys(sourcePost.meta).length > 0) {
			rewriteMetaCapabilityError(error, 'page duplication');
		}
		throw error;
	}
}

async function toolDeletePage(argsInput = {}) {
	const postId = Number(argsInput.post_id || 0);
	if (!postId) {
		throw toolError('missing_post_id', 'The post_id parameter is required.');
	}

	const existing = await fetchPostById(postId, String(argsInput.post_type || ''));
	const response = await requestJson(`${getRouteForType(existing.post_type)}/${postId}`, {
		method: 'DELETE',
		query: { force: argsInput.force === false ? 'false' : 'true' },
	});

	return {
		success: !!response?.deleted,
		post_id: postId,
		post_type: existing.post_type,
	};
}

async function callRestTool(name, argsInput) {
	switch (name) {
		case 'get_page':
			return toolGetPage(argsInput);
		case 'get_page_by_slug':
			return toolGetPageBySlug(argsInput);
		case 'get_page_id_by_slug':
			return toolGetPageIdBySlug(argsInput);
		case 'download_page_to_file':
			return toolDownloadPageToFile(argsInput);
		case 'update_page_from_file':
			return toolUpdatePageFromFile(argsInput);
		case 'create_page':
			return toolCreatePage(argsInput);
		case 'update_page':
			return toolUpdatePage(argsInput);
		case 'duplicate_page':
			return toolDuplicatePage(argsInput);
		case 'delete_page':
			return toolDeletePage(argsInput);
		default:
			throw toolError('unknown_tool', `Unknown tool "${name}".`);
	}
}

async function handleEndpointMessage(rawMessage) {
	let message;
	try {
		message = JSON.parse(rawMessage);
	} catch {
		sendError(null, -32700, 'Parse error');
		return;
	}

	const method = message.method || '';
	const id = message.id ?? null;
	const path = await getEndpointPath(MCP_REST_PATH);
	const isHttps = wpUrl.protocol === 'https:';
	const payload = JSON.stringify(message);
	const headers = {
		'Content-Type': 'application/json',
		Accept: 'application/json',
		Authorization: authHeader,
		'User-Agent': USER_AGENT,
		'Content-Length': Buffer.byteLength(payload),
	};

	if (sessionId) {
		headers['Mcp-Session-Id'] = sessionId;
	}

	try {
		const response = await doHttpRequest(
			{
				hostname: wpUrl.hostname,
				port: wpUrl.port || (isHttps ? 443 : 80),
				path,
				method: 'POST',
				headers,
			},
			payload,
		);

		if (response.statusCode >= 400) {
			let parsedBody = null;
			if (response.body.trim() !== '') {
				try {
					parsedBody = JSON.parse(response.body);
				} catch {
					parsedBody = response.body;
				}
			}

			sendError(id, -32603, 'Bridge error', {
				status: response.statusCode,
				message: typeof parsedBody?.message === 'string' ? parsedBody.message : `HTTP ${response.statusCode}`,
				body: parsedBody,
				path,
			});
			return;
		}

		const establishedSessionId = response.headers['mcp-session-id'];
		if (method === 'initialize' && typeof establishedSessionId === 'string' && establishedSessionId) {
			sessionId = establishedSessionId;
		}

		if (!response.body.trim()) {
			return;
		}

		let output = response.body.trim();
		if (method === 'initialize') {
			try {
				const parsed = JSON.parse(output);
				if (parsed?.result?.protocolVersion) {
					parsed.result.protocolVersion = PROTOCOL_VERSION;
					output = JSON.stringify(parsed);
				}
			} catch {}
		}

		writeFramed(output);
	} catch (error) {
		sendError(id, -32603, 'Bridge error', { details: String(error?.message || error) });
	}
}

async function handleRestMessage(rawMessage) {
	let message;
	try {
		message = JSON.parse(rawMessage);
	} catch {
		sendError(null, -32700, 'Parse error');
		return;
	}

	const method = message.method || '';
	const id = message.id ?? null;

	if (method === 'notifications/initialized') {
		return;
	}

	if (method === 'initialize') {
		sendSuccess(id, {
			protocolVersion: PROTOCOL_VERSION,
			capabilities: {
				tools: {},
			},
			serverInfo: {
				name: 'elementor-mcp-cli',
				version: CLI_VERSION,
			},
		});
		return;
	}

	if (method === 'ping') {
		sendSuccess(id, {});
		return;
	}

	if (method === 'tools/list') {
		sendSuccess(id, { tools: REST_TOOLS });
		return;
	}

	if (method === 'tools/call') {
		const toolName = message.params?.name;
		const toolArgs = isObject(message.params?.arguments) ? message.params.arguments : {};

		try {
			const result = await callRestTool(toolName, toolArgs);
			sendSuccess(id, {
				content: [
					{
						type: 'text',
						text: JSON.stringify(result, null, 2),
					},
				],
				structuredContent: result,
			});
		} catch (error) {
			const payload = {
				error: {
					code: error?.toolCode || 'tool_error',
					message: error?.message || 'Unknown tool error',
				},
			};

			if (error?.toolData !== undefined) {
				payload.error.data = error.toolData;
			}

			sendSuccess(id, {
				content: [
					{
						type: 'text',
						text: JSON.stringify(payload, null, 2),
					},
				],
				structuredContent: payload,
				isError: true,
			});
		}
		return;
	}

	sendError(id, -32601, `Method not found: ${method}`);
}

function tryParseFrames() {
	while (true) {
		let headerEnd = stdinBuffer.indexOf('\r\n\r\n');
		let separatorLength = 4;

		if (headerEnd === -1) {
			headerEnd = stdinBuffer.indexOf('\n\n');
			separatorLength = 2;
		}

		if (headerEnd === -1) {
			return;
		}

		const headerText = stdinBuffer.slice(0, headerEnd).toString('utf8');
		const contentLengthLine = headerText
			.split(/\r?\n/)
			.find((line) => line.toLowerCase().startsWith('content-length:'));

		if (!contentLengthLine) {
			log(`Dropping frame without Content-Length header: ${JSON.stringify(headerText.slice(0, 200))}`);
			stdinBuffer = stdinBuffer.slice(headerEnd + separatorLength);
			continue;
		}

		const contentLength = Number(contentLengthLine.split(':')[1]?.trim() || 0);
		const frameEnd = headerEnd + separatorLength + contentLength;

		if (stdinBuffer.length < frameEnd) {
			return;
		}

		const body = stdinBuffer.slice(headerEnd + separatorLength, frameEnd).toString('utf8');
		stdinBuffer = stdinBuffer.slice(frameEnd);
		messageQueue = messageQueue.then(() => (MODE === 'rest' ? handleRestMessage(body) : handleEndpointMessage(body)));
	}
}

log(`Starting elementor-mcp-cli mode=${MODE} wp=${WP_URL}`);
process.stdin.on('data', (chunk) => {
	stdinBuffer = Buffer.concat([stdinBuffer, chunk]);
	tryParseFrames();
});
process.stdin.on('end', () => {
	messageQueue.finally(() => process.exit(0));
});
process.on('SIGINT', () => process.exit(0));
process.on('SIGTERM', () => process.exit(0));
