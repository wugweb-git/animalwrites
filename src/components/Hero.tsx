import { Mail, Heart, PenLine, Camera } from "lucide-react";
import { Button } from "./ui/button";
import { motion } from "motion/react";
import { ImageWithFallback } from "./figma/ImageWithFallback";

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
          className="absolute top-20 left-10 w-32 h-32 bg-[#F59E0B] rounded-full opacity-5"
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
          className="absolute bottom-20 right-10 w-40 h-40 bg-[#F59E0B] rounded-full opacity-5"
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
                  className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl leading-tight"
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.6, delay: 0.2 }}
                  style={{
                    background: "linear-gradient(135deg, #333333 0%, #000000 50%, #333333 100%)",
                    backgroundSize: "200% 200%",
                    WebkitBackgroundClip: "text",
                    WebkitTextFillColor: "transparent",
                    backgroundClip: "text",
                  }}
                >
                  <motion.span
                    animate={{
                      backgroundPosition: ["0% 50%", "100% 50%", "0% 50%"]
                    }}
                    transition={{
                      duration: 5,
                      repeat: Infinity,
                      ease: "linear"
                    }}
                    style={{
                      background: "linear-gradient(135deg, #333333 0%, #000000 50%, #333333 100%)",
                      backgroundSize: "200% 200%",
                      WebkitBackgroundClip: "text",
                      WebkitTextFillColor: "transparent",
                      backgroundClip: "text",
                      display: "inline-block"
                    }}
                  >
                    Animal Writes
                  </motion.span>
                </motion.h1>
                <motion.p
                  className="text-lg sm:text-xl md:text-2xl text-[#F59E0B]"
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
                    <Mail className="w-5 h-5 text-[#F59E0B]" aria-hidden="true" />
                  </motion.div>
                  <span>29 November 2025</span>
                </div>
                <div className="flex items-center gap-3 justify-center md:justify-start">
                  <motion.div
                    className="inline-flex items-center gap-2 bg-white px-3 py-1.5 rounded-full text-xs md:text-sm border border-gray-200"
                    whileHover={{ scale: 1.05 }}
                    transition={{ type: "spring", stiffness: 400 }}
                  >
                    <Heart className="w-5 h-5 text-[#F59E0B]" aria-hidden="true" />
                  </motion.div>
                  <span>All GPOs and HPOs</span>
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
                    className="bg-[#F59E0B] hover:bg-[#D97706] text-white px-6 sm:px-8 py-5 sm:py-6 text-base sm:text-lg shadow-lg hover:shadow-xl transition-all w-full sm:w-auto"
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
                    onClick={() => scrollToSection("upload-receipt")}
                    className="bg-[#333333] hover:bg-[#1a1a1a] text-white px-6 sm:px-8 py-5 sm:py-6 text-base sm:text-lg shadow-lg hover:shadow-xl transition-all w-full sm:w-auto border-2 border-[#F59E0B]"
                    aria-label="Navigate to upload receipt section"
                  >
                    <Camera className="w-5 h-5 mr-2" aria-hidden="true" />
                    Upload Receipt
                  </Button>
                </motion.div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.6, delay: 0.7 }}
                className="flex items-center gap-2 justify-center md:justify-start text-sm text-[#666666]"
              >
                <span>Want to lead in your city?</span>
                <button
                  onClick={() => scrollToSection("volunteer")}
                  className="text-[#F59E0B] hover:text-[#D97706] underline underline-offset-2 hover:underline-offset-4 transition-all focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:ring-offset-2 rounded"
                  aria-label="Navigate to volunteer section"
                >
                  Become a coordinator
                </button>
              </motion.div>

              <motion.p
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.6, delay: 0.8 }}
                className="text-sm text-[#999999] italic max-w-md mx-auto md:mx-0"
              >
                A small voice. Sealed with compassion. Posted for justice.
              </motion.p>
            </motion.div>

            {/* Right Column - Image */}
            <motion.div
              initial={{ opacity: 0, x: 30 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              className="flex justify-center items-center order-first md:order-last"
            >
              <div className="relative w-full max-w-md">
                <motion.div
                  className="absolute inset-0 bg-[#F59E0B] opacity-10 rounded-3xl blur-2xl"
                  animate={{ scale: [1, 1.1, 1] }}
                  transition={{ duration: 5, repeat: Infinity, ease: "easeInOut" }}
                  aria-hidden="true"
                />
                <motion.div
                  className="relative rounded-2xl overflow-hidden shadow-2xl border-4 border-white"
                  whileHover={{ y: -8, scale: 1.02 }}
                  transition={{ type: "spring", stiffness: 300 }}
                >
                  <ImageWithFallback
                    src="https://images.unsplash.com/photo-1732812088418-f2fe47b11448?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxpbmRpYW4lMjBzdHJlZXQlMjBkb2clMjBjb21wYXNzaW9ufGVufDF8fHx8MTc2MzIxOTQyMHww&ixlib=rb-4.1.0&q=80&w=1080"
                    alt="Indian street dog symbolizing compassion and the animal lives Animal Writes campaign seeks to protect through Supreme Court petition"
                    className="w-full h-full object-cover aspect-square"
                  />
                  <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-6">
                    <p className="text-white text-center text-lg">
                      For Every Voiceless Life
                    </p>
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