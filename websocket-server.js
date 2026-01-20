const WebSocket = require('ws');
const wss = new WebSocket.Server({ port: 8080 });

console.log("WebSocket server running on ws://localhost:8080");

wss.on('connection', socket => {
    console.log("Client connected");

    socket.send("Welcome!");

    socket.on('message', msg => {
        console.log("Client says:", msg);
        socket.send("Server received: " + msg);
    });
});
