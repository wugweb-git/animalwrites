import { Hono } from "npm:hono";
import { cors } from "npm:hono/cors";
import { logger } from "npm:hono/logger";
import { createClient } from "jsr:@supabase/supabase-js@2";
import * as kv from "./kv_store.tsx";

const app = new Hono();

// Initialize Supabase client
const supabase = createClient(
  Deno.env.get("SUPABASE_URL") ?? "",
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY") ?? ""
);

// Initialize storage bucket on startup
const RECEIPTS_BUCKET = "make-7a8516f4-receipts";

async function initializeStorage() {
  try {
    console.log("Initializing storage bucket...");
    
    const { data: buckets, error: listError } = await supabase.storage.listBuckets();
    
    if (listError) {
      console.error("Error listing storage buckets:", {
        message: listError.message,
        name: listError.name,
        details: listError,
      });
      return;
    }
    
    console.log("Available buckets:", buckets?.map(b => b.name).join(", ") || "none");
    
    const bucketExists = buckets?.some(bucket => bucket.name === RECEIPTS_BUCKET);
    
    if (!bucketExists) {
      console.log(`Creating storage bucket: ${RECEIPTS_BUCKET}`);
      
      const { data: createData, error: createError } = await supabase.storage.createBucket(RECEIPTS_BUCKET, {
        public: false,
        fileSizeLimit: 5242880, // 5MB
        allowedMimeTypes: ["image/jpeg", "image/jpg", "image/png", "image/webp", "image/heic"],
      });
      
      if (createError) {
        console.error("Error creating storage bucket:", {
          message: createError.message,
          name: createError.name,
          details: createError,
        });
      } else {
        console.log(`Storage bucket '${RECEIPTS_BUCKET}' created successfully:`, createData);
      }
    } else {
      console.log(`Storage bucket '${RECEIPTS_BUCKET}' already exists`);
    }
  } catch (error) {
    console.error("Error initializing storage (caught exception):", {
      error,
      message: error instanceof Error ? error.message : "Unknown error",
      stack: error instanceof Error ? error.stack : undefined,
    });
  }
}

// Initialize storage on startup
initializeStorage();

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

    // Convert base64 to binary for storage
    const base64Data = receiptImage.split(",")[1] || receiptImage;
    const binaryData = Uint8Array.from(atob(base64Data), c => c.charCodeAt(0));

    // Generate unique filename for storage - add random string to prevent collisions
    const randomString = Math.random().toString(36).substring(2, 10);
    const fileExtension = fileName?.split(".").pop() || "jpg";
    const cleanEmail = email.replace(/[^a-zA-Z0-9]/g, "_");
    const storagePath = `${Date.now()}-${randomString}-${cleanEmail}.${fileExtension}`;

    // Verify bucket exists before uploading
    try {
      const { data: buckets, error: listError } = await supabase.storage.listBuckets();
      if (listError) {
        console.error("Error listing buckets:", listError);
        throw new Error(`Storage access error: ${listError.message}`);
      }
      
      const bucketExists = buckets?.some(bucket => bucket.name === RECEIPTS_BUCKET);
      if (!bucketExists) {
        console.error(`Bucket ${RECEIPTS_BUCKET} does not exist`);
        throw new Error("Storage bucket not initialized. Please contact support.");
      }
    } catch (verifyError) {
      console.error("Bucket verification failed:", verifyError);
      throw new Error(`Storage verification failed: ${verifyError instanceof Error ? verifyError.message : "Unknown error"}`);
    }

    // Upload to Supabase Storage with upsert: true to handle duplicates
    const { data: uploadData, error: uploadError } = await supabase.storage
      .from(RECEIPTS_BUCKET)
      .upload(storagePath, binaryData, {
        contentType: fileType || "image/jpeg",
        upsert: true, // Allow overwriting if filename somehow collides
        cacheControl: "3600",
      });

    if (uploadError) {
      console.error("Supabase Storage upload error details:", {
        message: uploadError.message,
        name: uploadError.name,
        cause: uploadError.cause,
        bucket: RECEIPTS_BUCKET,
        path: storagePath,
      });
      throw new Error(`Failed to upload image to storage: ${uploadError.message}`);
    }

    console.log("Storage upload successful:", uploadData);

    // Store receipt metadata in KV store (without the image data)
    const receiptData = {
      fullName,
      email,
      city,
      storagePath, // Reference to the file in storage
      fileName,
      fileType,
      submittedAt: receiptTimestamp,
    };

    await kv.set(receiptKey, receiptData);

    console.log(`Receipt uploaded: ${fullName} from ${city} (${fileName}) -> Storage: ${storagePath}`);

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

    // Generate signed URLs for each receipt image (valid for 1 hour)
    const receiptsWithUrls = await Promise.all(
      sortedReceipts.map(async (receipt) => {
        try {
          const { data: signedUrlData, error: urlError } = await supabase.storage
            .from(RECEIPTS_BUCKET)
            .createSignedUrl(receipt.storagePath, 3600); // 1 hour expiry

          if (urlError) {
            console.error(`Error generating signed URL for ${receipt.storagePath}:`, urlError);
            return {
              ...receipt,
              receiptImageUrl: null,
              error: "Failed to generate image URL",
            };
          }

          return {
            ...receipt,
            receiptImageUrl: signedUrlData.signedUrl,
          };
        } catch (error) {
          console.error(`Error processing receipt ${receipt.email}:`, error);
          return {
            ...receipt,
            receiptImageUrl: null,
            error: "Failed to process receipt",
          };
        }
      })
    );

    return c.json({
      success: true,
      count: receiptsWithUrls.length,
      receipts: receiptsWithUrls,
    });
  } catch (error) {
    console.error("Error fetching receipts:", error);
    return c.json({ error: "Failed to fetch receipts" }, 500);
  }
});

// Visitor counter endpoint - increment and get total visitors
app.post("/make-server-7a8516f4/visitor/count", async (c) => {
  try {
    const VISITOR_KEY = "site:visitor_count";
    
    // Get current count
    const currentCount = await kv.get(VISITOR_KEY);
    
    // If counter doesn't exist or is very low (less than 200K), initialize to 200K
    let newCount;
    if (currentCount === null || currentCount === undefined || currentCount < 200000) {
      newCount = 200000;
      console.log(`Initializing visitor count to ${newCount} (was: ${currentCount})`);
    } else {
      newCount = currentCount + 1;
    }
    
    // Update count
    await kv.set(VISITOR_KEY, newCount);
    
    console.log(`Visitor count updated: ${newCount}`);
    
    return c.json({
      success: true,
      count: newCount,
    });
  } catch (error) {
    console.error("Error updating visitor count:", error);
    return c.json({ error: "Failed to update visitor count" }, 500);
  }
});

// Get visitor count without incrementing
app.get("/make-server-7a8516f4/visitor/count", async (c) => {
  try {
    const VISITOR_KEY = "site:visitor_count";
    const count = await kv.get(VISITOR_KEY) || 0;
    
    return c.json({
      success: true,
      count: count,
    });
  } catch (error) {
    console.error("Error fetching visitor count:", error);
    return c.json({ error: "Failed to fetch visitor count" }, 500);
  }
});

// Initialize visitor count to a specific value (admin use)
app.post("/make-server-7a8516f4/visitor/initialize", async (c) => {
  try {
    const body = await c.req.json();
    const { count } = body;

    if (typeof count !== "number" || count < 0) {
      return c.json({ error: "Invalid count value. Must be a positive number." }, 400);
    }

    const VISITOR_KEY = "site:visitor_count";
    await kv.set(VISITOR_KEY, count);
    
    console.log(`Visitor count initialized to: ${count}`);
    
    return c.json({
      success: true,
      message: `Visitor count initialized to ${count}`,
      count: count,
    });
  } catch (error) {
    console.error("Error initializing visitor count:", error);
    return c.json({ error: "Failed to initialize visitor count" }, 500);
  }
});

// Get visitor count from Google Analytics API
app.get("/make-server-7a8516f4/analytics/visitors", async (c) => {
  try {
    const gaCredentials = Deno.env.get("GOOGLE_ANALYTICS_CREDENTIALS");
    const gaPropertyId = Deno.env.get("GA_PROPERTY_ID");

    if (!gaCredentials || !gaPropertyId) {
      console.error("Missing Google Analytics credentials or property ID");
      return c.json({ 
        error: "Google Analytics not configured",
        message: "Please configure GOOGLE_ANALYTICS_CREDENTIALS and GA_PROPERTY_ID environment variables"
      }, 500);
    }

    // Parse the service account credentials
    let credentials;
    try {
      credentials = JSON.parse(gaCredentials);
      
      // Validate that it's a proper service account JSON
      if (!credentials.private_key || !credentials.client_email || !credentials.project_id) {
        console.error("Invalid service account format - missing required fields");
        return c.json({ 
          error: "Invalid credentials format",
          message: "GOOGLE_ANALYTICS_CREDENTIALS must be a complete Google Cloud Service Account JSON key file with private_key, client_email, and project_id fields"
        }, 500);
      }
    } catch (parseError) {
      console.error("Error parsing GA credentials:", parseError);
      return c.json({ 
        error: "Invalid credentials format",
        message: "GOOGLE_ANALYTICS_CREDENTIALS must be valid JSON from a Google Cloud Service Account key file, not a password or string"
      }, 500);
    }

    // Create JWT for Google API authentication
    const header = {
      alg: "RS256",
      typ: "JWT",
    };

    const now = Math.floor(Date.now() / 1000);
    const payload = {
      iss: credentials.client_email,
      scope: "https://www.googleapis.com/auth/analytics.readonly",
      aud: "https://oauth2.googleapis.com/token",
      exp: now + 3600,
      iat: now,
    };

    // Import crypto for signing
    const crypto = await import("node:crypto");
    
    // Create JWT token
    const base64url = (str: string) => 
      btoa(str).replace(/\+/g, "-").replace(/\//g, "_").replace(/=/g, "");
    
    const headerBase64 = base64url(JSON.stringify(header));
    const payloadBase64 = base64url(JSON.stringify(payload));
    const signatureInput = `${headerBase64}.${payloadBase64}`;
    
    // Sign with private key
    const sign = crypto.createSign("RSA-SHA256");
    sign.update(signatureInput);
    sign.end();
    const signature = sign.sign(credentials.private_key, "base64");
    const signatureBase64 = signature.replace(/\+/g, "-").replace(/\//g, "_").replace(/=/g, "");
    
    const jwt = `${signatureInput}.${signatureBase64}`;

    // Exchange JWT for access token
    const tokenResponse = await fetch("https://oauth2.googleapis.com/token", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=${jwt}`,
    });

    if (!tokenResponse.ok) {
      const errorText = await tokenResponse.text();
      console.error("Token exchange failed:", errorText);
      return c.json({ 
        error: "Failed to authenticate with Google",
        details: errorText
      }, 500);
    }

    const tokenData = await tokenResponse.json();
    const accessToken = tokenData.access_token;

    // Fetch analytics data from GA4 Data API
    const analyticsResponse = await fetch(
      `https://analyticsdata.googleapis.com/v1beta/properties/${gaPropertyId}:runReport`,
      {
        method: "POST",
        headers: {
          "Authorization": `Bearer ${accessToken}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          dateRanges: [
            {
              startDate: "2020-01-01", // Get all-time data
              endDate: "today",
            },
          ],
          metrics: [
            { name: "totalUsers" }, // Total unique users all-time
            { name: "sessions" }, // Total sessions
          ],
        }),
      }
    );

    if (!analyticsResponse.ok) {
      const errorText = await analyticsResponse.text();
      console.error("Analytics API failed:", errorText);
      return c.json({ 
        error: "Failed to fetch analytics data",
        details: errorText
      }, 500);
    }

    const analyticsData = await analyticsResponse.json();
    
    // Extract the metrics
    const totalUsers = analyticsData.rows?.[0]?.metricValues?.[0]?.value || "0";
    const totalSessions = analyticsData.rows?.[0]?.metricValues?.[1]?.value || "0";

    console.log(`Analytics data fetched - Users: ${totalUsers}, Sessions: ${totalSessions}`);

    return c.json({
      success: true,
      visitors: parseInt(totalUsers, 10),
      sessions: parseInt(totalSessions, 10),
      source: "google_analytics",
    });

  } catch (error) {
    console.error("Error fetching Google Analytics data:", error);
    return c.json({ 
      error: "Failed to fetch visitor count from Google Analytics",
      details: error instanceof Error ? error.message : "Unknown error"
    }, 500);
  }
});

Deno.serve(app.fetch);