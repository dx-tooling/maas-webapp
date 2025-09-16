#!/usr/bin/env node
/* eslint-disable @typescript-eslint/no-require-imports */
/**
 * MCP Instance Data Registry Client for Node.js
 *
 * This module provides functions to interact with the MCP Instance Data Registry
 * from within a Docker container running Node.js.
 *
 * Usage as module:
 *   const registry = require('./registry-client');
 *   const value = await registry.get('database_url');
 *
 * Usage from command line:
 *   node registry-client.js <key>
 *   node registry-client.js --all
 */

const https = require("https");
const http = require("http");
const url = require("url");

/**
 * Get a value from the registry
 * @param {string} key - The key to retrieve
 * @returns {Promise<string|null>} The value or null if not found
 */
async function get(key) {
    // Get environment variables
    const registryEndpoint = process.env.REGISTRY_ENDPOINT;
    const registryBearer = process.env.REGISTRY_BEARER;
    const instanceUuid = process.env.INSTANCE_UUID;

    // Validate environment variables
    if (!registryEndpoint) {
        throw new Error("REGISTRY_ENDPOINT environment variable is not set");
    }
    if (!registryBearer) {
        throw new Error("REGISTRY_BEARER environment variable is not set");
    }
    if (!instanceUuid) {
        throw new Error("INSTANCE_UUID environment variable is not set");
    }

    // Build the URL
    const requestUrl = `${registryEndpoint}/${key}`;
    const parsedUrl = url.parse(requestUrl);

    // Choose http or https module
    const client = parsedUrl.protocol === "https:" ? https : http;

    return new Promise((resolve, reject) => {
        const options = {
            hostname: parsedUrl.hostname,
            port: parsedUrl.port,
            path: parsedUrl.path,
            method: "GET",
            headers: {
                Authorization: `Bearer ${registryBearer}`,
                Accept: "application/json",
            },
        };

        const req = client.request(options, (res) => {
            let data = "";

            res.on("data", (chunk) => {
                data += chunk;
            });

            res.on("end", () => {
                if (res.statusCode === 200) {
                    try {
                        const json = JSON.parse(data);
                        resolve(json.value);
                    } catch {
                        reject(new Error("Invalid JSON response"));
                    }
                } else if (res.statusCode === 404) {
                    resolve(null); // Key not found
                } else if (res.statusCode === 401) {
                    reject(new Error("Authentication failed"));
                } else {
                    reject(new Error(`HTTP error ${res.statusCode}: ${data}`));
                }
            });
        });

        req.on("error", (e) => {
            reject(new Error(`Request failed: ${e.message}`));
        });

        req.end();
    });
}

/**
 * Get all values from the registry for this instance
 * @returns {Promise<Object>} Object with all key-value pairs
 */
async function getAll() {
    // Get environment variables
    const registryEndpoint = process.env.REGISTRY_ENDPOINT;
    const registryBearer = process.env.REGISTRY_BEARER;
    const instanceUuid = process.env.INSTANCE_UUID;

    // Validate environment variables
    if (!registryEndpoint) {
        throw new Error("REGISTRY_ENDPOINT environment variable is not set");
    }
    if (!registryBearer) {
        throw new Error("REGISTRY_BEARER environment variable is not set");
    }
    if (!instanceUuid) {
        throw new Error("INSTANCE_UUID environment variable is not set");
    }

    // Build the URL
    const requestUrl = registryEndpoint;
    const parsedUrl = url.parse(requestUrl);

    // Choose http or https module
    const client = parsedUrl.protocol === "https:" ? https : http;

    return new Promise((resolve, reject) => {
        const options = {
            hostname: parsedUrl.hostname,
            port: parsedUrl.port,
            path: parsedUrl.path,
            method: "GET",
            headers: {
                Authorization: `Bearer ${registryBearer}`,
                Accept: "application/json",
            },
        };

        const req = client.request(options, (res) => {
            let data = "";

            res.on("data", (chunk) => {
                data += chunk;
            });

            res.on("end", () => {
                if (res.statusCode === 200) {
                    try {
                        const json = JSON.parse(data);
                        resolve(json.values || {});
                    } catch {
                        reject(new Error("Invalid JSON response"));
                    }
                } else if (res.statusCode === 401) {
                    reject(new Error("Authentication failed"));
                } else {
                    reject(new Error(`HTTP error ${res.statusCode}: ${data}`));
                }
            });
        });

        req.on("error", (e) => {
            reject(new Error(`Request failed: ${e.message}`));
        });

        req.end();
    });
}

// Export for use as module
module.exports = {
    get,
    getAll,
};

// Command-line interface
if (require.main === module) {
    const args = process.argv.slice(2);

    if (args.length === 0) {
        console.error("Usage: node registry-client.js <key>");
        console.error("   or: node registry-client.js --all");
        process.exit(1);
    }

    (async () => {
        try {
            if (args[0] === "--all") {
                const values = await getAll();
                console.log(JSON.stringify(values, null, 2));
            } else {
                const value = await get(args[0]);
                if (value !== null) {
                    console.log(value);
                } else {
                    console.error(`Key '${args[0]}' not found in registry`);
                    process.exit(1);
                }
            }
        } catch (e) {
            console.error(`Error: ${e.message}`);
            process.exit(1);
        }
    })();
}
