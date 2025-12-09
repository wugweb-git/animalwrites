import { useState, useEffect } from "react";
import { Eye } from "lucide-react";
import { motion } from "motion/react";
import { projectId, publicAnonKey } from "../utils/supabase/info";

export function VisitorCounter() {
  const [count, setCount] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // Increment visitor count on mount
    const incrementVisitor = async () => {
      try {
        const response = await fetch(
          `https://${projectId}.supabase.co/functions/v1/make-server-7a8516f4/visitor/count`,
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Authorization: `Bearer ${publicAnonKey}`,
            },
          }
        );

        if (!response.ok) {
          console.error("Failed to increment visitor count");
          return;
        }

        const data = await response.json();
        if (data.success && typeof data.count === "number") {
          setCount(data.count);
        }
      } catch (error) {
        console.error("Error incrementing visitor count:", error);
      } finally {
        setLoading(false);
      }
    };

    incrementVisitor();
  }, []);

  // Format number with Indian number system (lakhs)
  const formatNumber = (num: number) => {
    // Show full number with commas in Indian format
    return num.toLocaleString("en-IN");
  };

  if (loading) {
    return (
      <div className="flex items-center gap-2 bg-white/5 border border-[#F59E0B]/20 rounded-full px-4 py-2">
        <Eye className="w-4 h-4 text-[#F59E0B]" aria-hidden="true" />
        <div className="flex items-center gap-2">
          <span className="text-xs text-gray-400">Visitors:</span>
          <div className="w-16 h-4 bg-gray-700/50 rounded animate-pulse" />
        </div>
      </div>
    );
  }

  if (count === null) {
    return null;
  }

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.8 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.5 }}
      className="flex items-center gap-2 bg-white/5 border border-[#F59E0B]/20 rounded-full px-4 py-2"
    >
      <Eye className="w-4 h-4 text-[#F59E0B]" aria-hidden="true" />
      <div className="flex items-center gap-2">
        <span className="text-xs text-gray-400">Visitors:</span>
        <motion.span
          key={count}
          initial={{ scale: 1.2, color: "#F59E0B" }}
          animate={{ scale: 1, color: "#ffffff" }}
          transition={{ duration: 0.3 }}
          className="text-sm font-semibold tabular-nums"
        >
          {formatNumber(count)}
        </motion.span>
      </div>
    </motion.div>
  );
}