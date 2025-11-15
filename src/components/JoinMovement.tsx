import { Facebook } from "lucide-react";
import { Button } from "./ui/button";
import { motion } from "motion/react";

export function JoinMovement() {
  return (
    <section
      className="py-12 md:py-16 bg-[#D32F2F]"
      aria-labelledby="join-movement-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center"
          >
            <h2 id="join-movement-heading" className="text-white text-2xl md:text-4xl mb-4">
              Join the Movement
            </h2>
            <p className="text-white/90 text-base md:text-lg mb-6 md:mb-8">
              Follow the official campaign page on Facebook
            </p>
            <motion.div
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
            >
              <Button
                asChild
                size="lg"
                className="bg-white text-[#D32F2F] hover:bg-white/90 text-base md:text-lg px-6 md:px-8 py-5 md:py-6 h-auto focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#D32F2F]"
              >
                <a
                  href="https://facebook.com/AnimalWritesIndia"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-3"
                  aria-label="Follow Animal Writes India on Facebook"
                >
                  <Facebook className="w-5 h-5 md:w-6 md:h-6" aria-hidden="true" />
                  <span className="hidden sm:inline">facebook.com/</span>AnimalWritesIndia
                </a>
              </Button>
            </motion.div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}