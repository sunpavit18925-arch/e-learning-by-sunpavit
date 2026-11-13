import { createApp } from "./app.js";

const PORT = process.env.PORT || 4000;
const app = createApp();

const server = app.listen(PORT, () => {
  console.log(`========================================================`);
  console.log(`🚀 Pabpla Ceremony Management Express API (TypeScript)`);
  console.log(`📡 Server running at http://localhost:${PORT}`);
  console.log(`📋 Documentation / Health check: http://localhost:${PORT}/`);
  console.log(`🔑 Demo API Endpoints: http://localhost:${PORT}/api/demo`);
  console.log(`🛡️  Strict Type Safety & In-Memory Store Active`);
  console.log(`========================================================`);
});

const gracefulShutdown = () => {
  console.log("\nReceived kill signal, shutting down Express gracefully...");
  server.close(() => {
    console.log("Closed out remaining connections.");
    process.exit(0);
  });
};

process.on("SIGTERM", gracefulShutdown);
process.on("SIGINT", gracefulShutdown);

