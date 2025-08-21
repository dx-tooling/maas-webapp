#  Architecture Book

What are general tech stack, architecture, and code design choices for this application?


## Dependency Management

The application pulls in and manages external dependecies in three different areas:


### PHP

PHP dependencies are managed through Composer, and as always, are stored at vendor/.


### Node.js
Command Line Node.js dependencies are managed through NPM, and as always, are stored at node_modules/. This includes tooling required during development and testing, like ESLint and Prettier, but also an additional TailwindCSS installation due to https://youtrack.jetbrains.com/issue/WEB-55647/Support-Tailwind-css-autocompletion-using-standalone-tailwind-CLI — "additional" means: in addition to the actually used TailwindCSS installation through the AssetMapper & symfonycasts/tailwind-bundle)


### AssetMapper
Frontend-only JavaScript dependencies are managed through the Symfony AssetMapper system (via importmaps), and as always, are stored at assets/vendor/.

## Runtime Architecture (High level)

- Reverse proxy: Traefik container on ports 80/443 terminates TLS and routes:
  - `app.mcp-as-a-service.com` → host nginx on port 8090 (Symfony app)
  - `mcp-<slug>.mcp-as-a-service.com` → per-instance container port 8080
  - `vnc-<slug>.mcp-as-a-service.com` → per-instance container port 6080
- Host web server: nginx listens on 8090 without TLS
- Instance lifecycle: One Docker container per instance, created/started by the Symfony app via a restricted Docker CLI wrapper
