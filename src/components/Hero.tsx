import { Mail, Heart, PenLine } from "lucide-react";
import { Button } from "./ui/button";
import { motion } from "motion/react";

export function Hero() {
  const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
  };

  return (
    <section
      className="relative overflow-hidden bg-[#FFF8F3] min-h-[90vh] flex items-center"
      aria-labelledby="hero-heading"
    >
      {/* Animated background elements */}
      <div className="absolute inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <motion.div
          className="absolute top-20 left-10 w-32 h-32 bg-[#D32F2F] rounded-full opacity-5"
          animate={{
            scale: [1, 1.2, 1],
            x: [0, 20, 0],
            y: [0, -20, 0],
          }}
          transition={{
            duration: 8,
            repeat: Infinity,
            ease: "easeInOut",
          }}
        />
        <motion.div
          className="absolute bottom-20 right-10 w-40 h-40 bg-[#D32F2F] rounded-full opacity-5"
          animate={{
            scale: [1, 1.3, 1],
            x: [0, -30, 0],
            y: [0, 30, 0],
          }}
          transition={{
            duration: 10,
            repeat: Infinity,
            ease: "easeInOut",
          }}
        />
      </div>

      {/* Content */}
      <div className="container mx-auto px-4 py-12 md:py-20 relative z-10">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-2 gap-8 md:gap-12 items-center">
            {/* Left Column - Text */}
            <motion.div
              initial={{ opacity: 0, x: -30 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6 }}
              className="space-y-6 md:space-y-8 text-center md:text-left"
            >
              <div className="space-y-3 md:space-y-4">
                <motion.h1
                  id="hero-heading"
                  className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl text-[#333333] leading-tight"
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.6, delay: 0.2 }}
                >
                  Animal Writes
                </motion.h1>
                <motion.p
                  className="text-lg sm:text-xl md:text-2xl text-[#D32F2F]"
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.6, delay: 0.3 }}
                >
                  India's Largest Letter Petition Campaign
                </motion.p>
              </div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.4 }}
                className="space-y-3 text-base sm:text-lg text-[#666666]"
              >
                <div className="flex items-center gap-3 justify-center md:justify-start">
                  <motion.div
                    whileHover={{ scale: 1.1, rotate: 5 }}
                    transition={{ type: "spring", stiffness: 400 }}
                  >
                    <Mail className="w-5 h-5 text-[#D32F2F]" aria-hidden="true" />
                  </motion.div>
                  <span>29 November 2025</span>
                </div>
                <div className="flex items-center gap-3 justify-center md:justify-start">
                  <motion.div
                    whileHover={{ scale: 1.2 }}
                    animate={{ scale: [1, 1.1, 1] }}
                    transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                  >
                    <Heart className="w-5 h-5 text-[#D32F2F]" aria-hidden="true" />
                  </motion.div>
                  <span>All GPOs Across India</span>
                </div>
              </motion.div>

              <motion.p
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.5 }}
                className="text-base sm:text-lg text-[#666666] leading-relaxed max-w-xl mx-auto md:mx-0"
              >
                A nationwide movement of empathy and peaceful civic action. Thousands will walk to their local post offices to mail letters for coexistence, not cruelty.
              </motion.p>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.6, delay: 0.6 }}
                className="flex flex-col sm:flex-row flex-wrap gap-4 justify-center md:justify-start"
              >
                <motion.div
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                >
                  <Button
                    onClick={() => scrollToSection("letter-templates")}
                    className="bg-[#D32F2F] hover:bg-[#B71C1C] text-white px-6 sm:px-8 py-5 sm:py-6 text-base sm:text-lg shadow-lg hover:shadow-xl transition-all w-full sm:w-auto"
                    aria-label="Navigate to letter templates section"
                  >
                    <PenLine className="w-5 h-5 mr-2" aria-hidden="true" />
                    Write Your Letter
                  </Button>
                </motion.div>
                <motion.div
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                >
                  <Button
                    onClick={() => scrollToSection("volunteer")}
                    variant="outline"
                    className="border-2 border-[#D32F2F] text-[#D32F2F] hover:bg-[#D32F2F] hover:text-white px-6 sm:px-8 py-5 sm:py-6 text-base sm:text-lg transition-all w-full sm:w-auto"
                    aria-label="Navigate to volunteer section"
                  >
                    Lead Your City
                  </Button>
                </motion.div>
              </motion.div>

              <motion.p
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.6, delay: 0.7 }}
                className="text-sm text-[#999999] italic max-w-md mx-auto md:mx-0"
              >
                A small voice. Sealed with compassion. Posted for justice.
              </motion.p>
            </motion.div>

            {/* Right Column - Illustration */}
            <motion.div
              initial={{ opacity: 0, x: 30 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              className="flex justify-center items-center order-first md:order-last"
            >
              <div className="relative w-full max-w-md">
                <motion.div
                  className="absolute inset-0 bg-[#D32F2F] opacity-5 rounded-3xl"
                  animate={{ rotate: [3, 5, 3] }}
                  transition={{ duration: 5, repeat: Infinity, ease: "easeInOut" }}
                  aria-hidden="true"
                />
                <motion.div
                  className="relative bg-white rounded-2xl shadow-xl p-6 sm:p-8 md:p-12 border border-gray-100"
                  whileHover={{ y: -5, shadow: "2xl" }}
                  transition={{ type: "spring", stiffness: 300 }}
                >
                  <div className="text-center space-y-4 md:space-y-6">
                    <motion.div
                      className="text-6xl sm:text-7xl md:text-8xl"
                      animate={{ scale: [1, 1.05, 1] }}
                      transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                      role="img"
                      aria-label="Envelope emoji"
                    >
                      ✉️
                    </motion.div>
                    <div className="space-y-2 md:space-y-3">
                      <p className="text-xl sm:text-2xl text-[#333333]">
                        Every Letter Counts
                      </p>
                      <p className="text-sm sm:text-base text-[#666666]">
                        Join thousands across India in this historic movement
                      </p>
                    </div>
                    <div className="flex justify-center gap-3 sm:gap-4 text-3xl sm:text-4xl">
                      <motion.span
                        animate={{ y: [0, -5, 0] }}
                        transition={{ duration: 2, repeat: Infinity, delay: 0 }}
                        role="img"
                        aria-label="Dog emoji"
                      >
                        🐕
                      </motion.span>
                      <motion.span
                        animate={{ scale: [1, 1.2, 1] }}
                        transition={{ duration: 1.5, repeat: Infinity, delay: 0.3 }}
                        role="img"
                        aria-label="Heart emoji"
                      >
                        ❤️
                      </motion.span>
                      <motion.span
                        animate={{ y: [0, -5, 0] }}
                        transition={{ duration: 2, repeat: Infinity, delay: 0.6 }}
                        role="img"
                        aria-label="Indian flag emoji"
                      >
                        🇮🇳
                      </motion.span>
                    </div>
                  </div>
                </motion.div>
              </div>
            </motion.div>
          </div>
        </div>
      </div>
    </section>
  );
}