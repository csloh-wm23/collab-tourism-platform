/** Cloudflare Worker entry point for the vinext-starter template. */
import { handleImageOptimization, DEFAULT_DEVICE_SIZES, DEFAULT_IMAGE_SIZES } from "vinext/server/image-optimization";
import handler from "vinext/server/app-router-entry";

interface Env {
  ASSETS: Fetcher;
  DB: D1Database;
  IMAGES: {
    input(stream: ReadableStream): {
      transform(options: Record<string, unknown>): {
        output(options: { format: string; quality: number }): Promise<{ response(): Response }>;
      };
    };
  };
}

interface ExecutionContext {
  waitUntil(promise: Promise<unknown>): void;
  passThroughOnException(): void;
}

type RecordRow = {
  id: number;
  type: string;
  owner: string;
  title: string;
  status: string;
  payload: string;
  created_at: string;
  updated_at: string;
};

async function ensureRecordsTable(db: D1Database) {
  await db.prepare(`CREATE TABLE IF NOT EXISTS records (
    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    type TEXT NOT NULL,
    owner TEXT NOT NULL DEFAULT 'demo-user',
    title TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'active',
    payload TEXT NOT NULL DEFAULT '{}',
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )`).run();
}

function recordJson(row: RecordRow) {
  let payload: Record<string, unknown> = {};
  try { payload = JSON.parse(row.payload); } catch { payload = {}; }
  return { id: row.id, type: row.type, owner: row.owner, title: row.title, status: row.status, payload, createdAt: row.created_at, updatedAt: row.updated_at };
}

async function handleRecords(request: Request, db: D1Database): Promise<Response> {
  await ensureRecordsTable(db);
  if (request.method === "GET") {
    const result = await db.prepare("SELECT * FROM records ORDER BY created_at DESC LIMIT 200").all<RecordRow>();
    return Response.json({ records: result.results.map(recordJson) });
  }
  if (request.method === "POST") {
    const body = await request.json() as { type?: string; title?: string; status?: string; payload?: Record<string, unknown> };
    const type = body.type?.trim() ?? "";
    const title = body.title?.trim() ?? "";
    if (!type || !title) return Response.json({ error: "type and title are required" }, { status: 400 });
    const now = new Date().toISOString();
    const inserted = await db.prepare("INSERT INTO records (type, owner, title, status, payload, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING *")
      .bind(type, "demo-user", title, body.status?.trim() || "active", JSON.stringify(body.payload ?? {}), now, now).first<RecordRow>();
    return Response.json({ record: inserted ? recordJson(inserted) : null }, { status: 201 });
  }
  if (request.method === "DELETE") {
    const id = Number(new URL(request.url).searchParams.get("id"));
    if (!Number.isInteger(id)) return Response.json({ error: "valid id required" }, { status: 400 });
    await db.prepare("DELETE FROM records WHERE id = ?").bind(id).run();
    return Response.json({ success: true });
  }
  return new Response("Method not allowed", { status: 405, headers: { Allow: "GET, POST, DELETE" } });
}

// Image security config. SVG sources with .svg extension auto-skip the
// optimization endpoint on the client side (served directly, no proxy).
// To route SVGs through the optimizer (with security headers), set
// dangerouslyAllowSVG: true in next.config.js and uncomment below:
// const imageConfig: ImageConfig = { dangerouslyAllowSVG: true };

const worker = {
  async fetch(request: Request, env: Env, ctx: ExecutionContext): Promise<Response> {
    const url = new URL(request.url);

    if (url.pathname === "/api/records") {
      try {
        return await handleRecords(request, env.DB);
      } catch (error) {
        return Response.json({ error: error instanceof Error ? error.message : "Database unavailable" }, { status: 500 });
      }
    }

    if (url.pathname === "/_vinext/image") {
      const allowedWidths = [...DEFAULT_DEVICE_SIZES, ...DEFAULT_IMAGE_SIZES];
      return handleImageOptimization(request, {
        fetchAsset: (path) => env.ASSETS.fetch(new Request(new URL(path, request.url))),
        transformImage: async (body, { width, format, quality }) => {
          const result = await env.IMAGES.input(body).transform(width > 0 ? { width } : {}).output({ format, quality });
          return result.response();
        },
      }, allowedWidths);
    }

    return handler.fetch(request, env, ctx);
  },
};

export default worker;
