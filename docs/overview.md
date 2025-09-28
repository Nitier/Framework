# Framework Overview

This framework bootstraps an HTTP kernel backed by a PSR-compliant stack:

- **PSR-7**: `Framework\Http\Message` содержит лёгкие реализации `ServerRequestInterface`, `ResponseInterface`, `StreamInterface`, `UploadedFileInterface`, `UriInterface`, а также готовые ответы (`JsonResponse`, `HtmlResponse`, `EmptyResponse`).
- **PSR-15**: Middleware and request handlers implement the `psr/http-server-middleware` and `psr/http-server-handler` standards via the `MiddlewarePipeline` and `CallableRequestHandler` helpers.
- **Dependency Injection**: All services are assembled through PHP-DI. Kernel-level configuration lives in `config/`, while application-specific overrides extend the container from `test-app/config/`.
- **Routing**: The `Router` class provides expressive route declaration, grouping, and middleware attachment, producing `RouteResult` instances consumed by the kernel.

The execution flow for a web request is:

1. `Kernel::handle()` receives a PSR-7 server request.
2. The router resolves a matching route (or reports a 404 / 405 failure).
3. Route and global middleware are assembled into a PSR-15 pipeline.
4. The resolved request handler (controller) executes and returns a PSR-7 response.
5. `Kernel::handle()` emits the response via the configured `ResponseEmitter`.

See the remaining documents in this directory for focused guidance on routing, middleware, and kernel configuration.
