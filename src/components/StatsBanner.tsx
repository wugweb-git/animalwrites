import { Mail } from "lucide-react";
import { motion } from "framer-motion";

export function StatsBanner() {
  return (
    <motion.div 
      initial={{ y: -100, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ duration: 0.6, ease: "easeOut" }}
      className="fixed top-16 md:top-20 left-0 right-0 z-40 bg-[#F59E0B] border-b border-[#D97706] shadow-sm"
      role="banner"
      aria-label="Campaign milestone"
    >
      <div className="container mx-auto px-4 py-2 md:py-2.5">
        <div className="flex items-center justify-center gap-2 text-center flex-wrap">
          <motion.div
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            transition={{ delay: 0.3, duration: 0.4, type: "spring" }}
          >
            <Mail className="w-4 h-4 md:w-5 md:h-5 text-[#333333]" aria-hidden="true" />
          </motion.div>
          <p className="text-sm md:text-base text-[#333333]">
            <motion.span 
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.5, duration: 0.5 }}
              className="font-semibold"
            >
              More than 2.5 Lakhs letters
            </motion.span> sent to Supreme Court
            <motion.span
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.8, duration: 0.5 }}
              className="ml-2 px-2 py-0.5 bg-[#333333] text-[#F59E0B] rounded text-xs md:text-sm font-semibold"
            >
              Ready for Phase 2.0
            </motion.span>
          </p>
        </div>
      </div>
    </motion.div>
  );
}