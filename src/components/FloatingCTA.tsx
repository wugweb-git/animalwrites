import { useState, useEffect } from "react";
import { Mail, X } from "lucide-react";
import { Button } from "./ui/button";
import { motion, AnimatePresence } from "motion/react";

export function FloatingCTA() {
  const [isVisible, setIsVisible] = useState(false);
  const [isDismissed, setIsDismissed] = useState(false);

  useEffect(() => {
    const handleScroll = () => {
      if (window.scrollY > 800 && !isDismissed) {
        setIsVisible(true);
      } else if (window.scrollY <= 800) {
        setIsVisible(false);
      }
    };

    window.addEventListener("scroll", handleScroll);
    return () => window.removeEventListener("scroll", handleScroll);
  }, [isDismissed]);

  const handleDismiss = () => {
    setIsDismissed(true);
  };

  const handleCTAClick = () => {
    const letterSection = document.getElementById("letter-templates");
    letterSection?.scrollIntoView({ behavior: "smooth" });
  };

  return (
    <AnimatePresence>
      {isVisible && !isDismissed && (
        <motion.div
          initial={{ y: 100, opacity: 0, scale: 0.8 }}
          animate={{ y: 0, opacity: 1, scale: 1 }}
          exit={{ y: 100, opacity: 0, scale: 0.8 }}
          transition={{ type: "spring", stiffness: 300, damping: 25 }}
          className="fixed bottom-4 right-4 left-4 md:left-auto md:bottom-6 md:right-6 z-50 max-w-md md:max-w-lg"
          role="complementary"
          aria-label="Quick action banner"
        >
          <motion.div
            className="bg-white rounded-2xl shadow-2xl p-4 md:p-5 border-2 border-[#D32F2F]/20 relative"
            whileHover={{ scale: 1.02 }}
            transition={{ type: "spring", stiffness: 400 }}
          >
            <motion.button
              onClick={handleDismiss}
              className="absolute -top-3 -right-3 bg-[#333333] text-white rounded-full p-2 hover:bg-[#1a1a1a] transition-colors focus:outline-none focus:ring-2 focus:ring-[#333333] focus:ring-offset-2 shadow-lg"
              aria-label="Dismiss call to action"
              whileHover={{ scale: 1.1, rotate: 90 }}
              whileTap={{ scale: 0.9 }}
            >
              <X className="w-4 h-4" aria-hidden="true" />
            </motion.button>
            
            <div className="flex flex-col sm:flex-row items-center gap-4">
              <motion.div
                className="bg-[#333333] rounded-full p-3 flex-shrink-0"
                animate={{
                  scale: [1, 1.1, 1],
                  rotate: [0, 5, -5, 0],
                }}
                transition={{
                  duration: 2,
                  repeat: Infinity,
                  ease: "easeInOut",
                }}
              >
                <Mail className="w-6 h-6 text-white" aria-hidden="true" />
              </motion.div>
              <div className="flex-1 text-center sm:text-left">
                <p className="text-[#333333] font-semibold">Ready to make a difference?</p>
                <p className="text-xs sm:text-sm text-[#666666]">Join thousands across India</p>
              </div>
              
              <motion.div
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                <Button
                  className="bg-[#333333] hover:bg-[#1a1a1a] text-white whitespace-nowrap focus:ring-2 focus:ring-[#333333] focus:ring-offset-2"
                  onClick={handleCTAClick}
                  aria-label="Navigate to write letter section"
                >
                  Write Your Letter
                </Button>
              </motion.div>
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}