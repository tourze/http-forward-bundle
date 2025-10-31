// 简单的Node.js SSE服务器
const http = require('http');

const server = http.createServer((req, res) => {
    if (req.url === '/events') {
        res.writeHead(200, {
            'Content-Type': 'text/event-stream',
            'Cache-Control': 'no-cache',
            'Connection': 'keep-alive',
            'Access-Control-Allow-Origin': '*'
        });

        let count = 0;
        const interval = setInterval(() => {
            res.write(`data: ${JSON.stringify({message: 'Hello', count: count++, time: new Date()})}\n\n`);
        }, 1000);

        req.on('close', () => clearInterval(interval));
    }
});

server.listen(3000);
