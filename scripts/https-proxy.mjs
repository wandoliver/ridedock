import { readFileSync } from 'node:fs';
import { request as createHttpRequest } from 'node:http';
import { createServer as createHttpsServer } from 'node:https';
import { fileURLToPath, URL } from 'node:url';

const listenHost = process.env.LOCAL_HTTPS_HOST ?? '127.0.0.1';
const listenPort = Number(process.env.LOCAL_HTTPS_PORT ?? 8001);
const targetHost = process.env.LOCAL_HTTP_HOST ?? '127.0.0.1';
const targetPort = Number(process.env.LOCAL_HTTP_PORT ?? 8002);

const certificate = fileURLToPath(new URL('../.certs/localhost.pem', import.meta.url));
const privateKey = fileURLToPath(new URL('../.certs/localhost-key.pem', import.meta.url));

const server = createHttpsServer(
    {
        cert: readFileSync(certificate),
        key: readFileSync(privateKey),
    },
    (request, response) => {
        const forwardedFor = [request.socket.remoteAddress, request.headers['x-forwarded-for']]
            .filter(Boolean)
            .join(', ');

        const proxyRequest = createHttpRequest(
            {
                hostname: targetHost,
                port: targetPort,
                method: request.method,
                path: request.url,
                headers: {
                    ...request.headers,
                    host: request.headers.host,
                    'x-forwarded-for': forwardedFor,
                    'x-forwarded-host': request.headers.host,
                    'x-forwarded-port': String(listenPort),
                    'x-forwarded-proto': 'https',
                },
            },
            (proxyResponse) => {
                response.writeHead(proxyResponse.statusCode ?? 502, proxyResponse.headers);
                proxyResponse.pipe(response);
            },
        );

        proxyRequest.on('error', (error) => {
            if (! response.headersSent) {
                response.writeHead(502, { 'content-type': 'text/plain; charset=utf-8' });
            }

            response.end(`RideDock development server is unavailable: ${error.message}\n`);
        });

        request.pipe(proxyRequest);
    },
);

server.on('clientError', (_error, socket) => {
    socket.end('HTTP/1.1 400 Bad Request\r\n\r\n');
});

server.listen(listenPort, listenHost, () => {
    console.log(`RideDock HTTPS proxy: https://${listenHost}:${listenPort}`);
    console.log(`Forwarding to: http://${targetHost}:${targetPort}`);
});
