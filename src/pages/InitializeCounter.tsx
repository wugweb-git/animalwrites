import { useState, useEffect } from "react";
import { projectId, publicAnonKey } from "../utils/supabase/info";

export function InitializeCounter() {
  const [count, setCount] = useState("200000");
  const [currentCount, setCurrentCount] = useState<number | null>(null);
  const [loading, setLoading] = useState(false);
  const [fetchingCurrent, setFetchingCurrent] = useState(true);
  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  // Fetch current count on mount
  useEffect(() => {
    const fetchCurrentCount = async () => {
      try {
        const response = await fetch(
          `https://${projectId}.supabase.co/functions/v1/make-server-7a8516f4/visitor/count`,
          {
            method: "GET",
            headers: {
              Authorization: `Bearer ${publicAnonKey}`,
            },
          }
        );

        if (response.ok) {
          const data = await response.json();
          setCurrentCount(data.count);
        }
      } catch (err) {
        console.error("Error fetching current count:", err);
      } finally {
        setFetchingCurrent(false);
      }
    };

    fetchCurrentCount();
  }, []);

  const handleInitialize = async () => {
    setLoading(true);
    setMessage("");
    setError("");

    try {
      const response = await fetch(
        `https://${projectId}.supabase.co/functions/v1/make-server-7a8516f4/visitor/initialize`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${publicAnonKey}`,
          },
          body: JSON.stringify({
            count: parseInt(count, 10),
          }),
        }
      );

      const data = await response.json();

      if (!response.ok) {
        setError(data.error || "Failed to initialize counter");
        return;
      }

      setMessage(`✅ Success! Visitor counter initialized to ${data.count.toLocaleString("en-IN")}`);
      setCurrentCount(data.count);
    } catch (err) {
      console.error("Error initializing counter:", err);
      setError("Network error. Please try again.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-white to-[#FFF8F3] flex items-center justify-center p-4">
      <div className="max-w-md w-full bg-white rounded-2xl shadow-xl p-8 border-2 border-[#F59E0B]/20">
        <h1 className="text-3xl text-[#333333] mb-2 text-center">
          Initialize Visitor Counter
        </h1>
        <p className="text-[#666666] text-sm text-center mb-6">
          Set the starting value for the visitor counter based on Google Analytics data
        </p>

        {/* Current Count Display */}
        <div className="bg-[#FFF8F3] border border-[#F59E0B]/30 rounded-lg p-4 mb-6">
          <div className="text-center">
            <p className="text-xs text-[#666666] mb-1">Current Counter Value</p>
            {fetchingCurrent ? (
              <div className="h-8 w-32 bg-gray-200 rounded animate-pulse mx-auto" />
            ) : (
              <p className="text-2xl text-[#333333]">
                {currentCount !== null ? currentCount.toLocaleString("en-IN") : "Not set"}
              </p>
            )}
          </div>
        </div>

        <div className="space-y-4">
          <div>
            <label htmlFor="count" className="block text-sm text-[#333333] mb-2">
              New Starting Count
            </label>
            <input
              id="count"
              type="number"
              value={count}
              onChange={(e) => setCount(e.target.value)}
              className="w-full px-4 py-3 rounded-lg border-2 border-gray-300 focus:border-[#F59E0B] focus:outline-none transition-colors"
              placeholder="200000"
              min="0"
            />
          </div>

          <button
            onClick={handleInitialize}
            disabled={loading || !count}
            className="w-full bg-[#F59E0B] hover:bg-[#D97706] text-white py-3 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-semibold"
          >
            {loading ? "Initializing..." : "Initialize Counter"}
          </button>

          {message && (
            <div className="bg-green-50 border-2 border-green-200 text-green-800 p-4 rounded-lg text-sm">
              {message}
            </div>
          )}

          {error && (
            <div className="bg-red-50 border-2 border-red-200 text-red-800 p-4 rounded-lg text-sm">
              ❌ {error}
            </div>
          )}
        </div>

        <div className="mt-6 pt-6 border-t border-gray-200">
          <p className="text-xs text-[#666666] text-center">
            💡 Based on your GA4 data: <strong>197K event count</strong>
            <br />
            Recommended starting value: <strong>200,000</strong>
          </p>
          <p className="text-xs text-[#999999] text-center mt-2">
            Note: This will set the counter to the specified value. Each new visitor will increment from this number.
          </p>
        </div>

        <div className="mt-4">
          <a 
            href="/" 
            className="block text-center text-sm text-[#F59E0B] hover:text-[#D97706] transition-colors"
          >
            ← Back to Main Site
          </a>
        </div>
      </div>
    </div>
  );
}