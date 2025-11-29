import { Hono } from "npm:hono";
import { cors } from "npm:hono/cors";
import { logger } from "npm:hono/logger";
import * as kv from "./kv_store.tsx";
const app = new Hono();

// Enable logger
app.use('*', logger(console.log));

// Enable CORS for all routes and methods
app.use(
  "/*",
  cors({
    origin: "*",
    allowHeaders: ["Content-Type", "Authorization"],
    allowMethods: ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    exposeHeaders: ["Content-Length"],
    maxAge: 600,
  }),
);

// Health check endpoint
app.get("/make-server-7a8516f4/health", (c) => {
  return c.json({ status: "ok" });
});

// Volunteer signup endpoint
app.post("/make-server-7a8516f4/volunteer-signup", async (c) => {
  try {
    const body = await c.req.json();
    const { name, email, city, role } = body;

    // Validate required fields
    if (!name || !email || !city || !role) {
      return c.json({ error: "All fields are required" }, 400);
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return c.json({ error: "Invalid email format" }, 400);
    }

    // Create unique key for this volunteer (using timestamp + email)
    const timestamp = new Date().toISOString();
    const volunteerKey = `volunteer:${timestamp}:${email}`;

    // Store volunteer data
    const volunteerData = {
      name,
      email,
      city,
      role,
      submittedAt: timestamp,
    };

    await kv.set(volunteerKey, volunteerData);

    console.log(`Volunteer signup saved: ${email} from ${city} as ${role}`);

    return c.json({
      success: true,
      message: "Volunteer signup received successfully",
      data: volunteerData,
    });
  } catch (error) {
    console.error("Error saving volunteer signup:", error);
    return c.json({ error: "Failed to save volunteer signup" }, 500);
  }
});

// Get all volunteers (for admin purposes)
app.get("/make-server-7a8516f4/volunteers", async (c) => {
  try {
    const volunteers = await kv.getByPrefix("volunteer:");
    
    // Sort by timestamp (newest first)
    const sortedVolunteers = volunteers
      .map((item) => item.value)
      .sort((a, b) => new Date(b.submittedAt).getTime() - new Date(a.submittedAt).getTime());

    return c.json({
      success: true,
      count: sortedVolunteers.length,
      volunteers: sortedVolunteers,
    });
  } catch (error) {
    console.error("Error fetching volunteers:", error);
    return c.json({ error: "Failed to fetch volunteers" }, 500);
  }
});

// Receipt upload endpoint
app.post("/make-server-7a8516f4/receipts", async (c) => {
  try {
    const body = await c.req.json();
    const { fullName, email, city, receiptImage, fileName, fileType, timestamp } = body;

    // Validate required fields
    if (!fullName || !email || !city || !receiptImage) {
      return c.json({ error: "All fields including receipt image are required" }, 400);
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return c.json({ error: "Invalid email format" }, 400);
    }

    // Create unique key for this receipt
    const receiptTimestamp = timestamp || new Date().toISOString();
    const receiptKey = `receipt:${receiptTimestamp}:${email}`;

    // Store receipt data (including base64 image)
    const receiptData = {
      fullName,
      email,
      city,
      receiptImage, // base64 encoded image
      fileName,
      fileType,
      submittedAt: receiptTimestamp,
    };

    await kv.set(receiptKey, receiptData);

    console.log(`Receipt uploaded: ${fullName} from ${city} (${fileName})`);

    return c.json({
      success: true,
      message: "Receipt uploaded successfully",
      data: {
        fullName,
        email,
        city,
        fileName,
        submittedAt: receiptTimestamp,
      },
    });
  } catch (error) {
    console.error("Error uploading receipt:", error);
    return c.json({ 
      error: "Failed to upload receipt", 
      details: error instanceof Error ? error.message : "Unknown error" 
    }, 500);
  }
});

// Get all receipts (for admin purposes)
app.get("/make-server-7a8516f4/receipts", async (c) => {
  try {
    const receipts = await kv.getByPrefix("receipt:");
    
    // Sort by timestamp (newest first)
    const sortedReceipts = receipts
      .map((item) => item.value)
      .sort((a, b) => new Date(b.submittedAt).getTime() - new Date(a.submittedAt).getTime());

    return c.json({
      success: true,
      count: sortedReceipts.length,
      receipts: sortedReceipts,
    });
  } catch (error) {
    console.error("Error fetching receipts:", error);
    return c.json({ error: "Failed to fetch receipts" }, 500);
  }
});

Deno.serve(app.fetch);