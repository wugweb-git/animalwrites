import { useState, useEffect } from "react";
import { motion, AnimatePresence } from "motion/react";
import { ArrowUp } from "lucide-react";

export function BackToTop() {
  const [isVisible, setIsVisible] = useState(false);

  useEffect(() => {
    const toggleVisibility = () => {
      if (window.pageYOffset > 500) {
        setIsVisible(true);
      } else {
        setIsVisible(false);
      }
    };

    window.addEventListener("scroll", toggleVisibility);

    return () => {
      window.removeEventListener("scroll", toggleVisibility);
    };
  }, []);

  const scrollToTop = () => {
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  };

  return (
    <AnimatePresence>
      {isVisible && (
        <motion.button
          initial={{ opacity: 0, scale: 0, y: 100 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0, y: 100 }}
          transition={{ type: "spring", stiffness: 300, damping: 20 }}
          onClick={scrollToTop}
          className="fixed bottom-20 md:bottom-8 right-4 md:right-8 z-40 bg-[#333333] text-white p-3 md:p-4 rounded-full shadow-lg hover:bg-[#1a1a1a] transition-colors focus:outline-none focus:ring-2 focus:ring-[#333333] focus:ring-offset-2"
          whileHover={{ scale: 1.1 }}
          whileTap={{ scale: 0.9 }}
          aria-label="Scroll back to top"
        >
          <motion.div
            animate={{ y: [0, -3, 0] }}
            transition={{ duration: 1.5, repeat: Infinity, ease: "easeInOut" }}
          >
            <ArrowUp className="w-5 h-5 md:w-6 md:h-6" aria-hidden="true" />
          </motion.div>
        </motion.button>
      )}
    </AnimatePresence>
  );
}