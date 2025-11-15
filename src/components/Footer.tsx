import { Mail, Heart } from "lucide-react";
import { motion } from "motion/react";

export function Footer() {
  return (
    <footer className="bg-[#333333] text-white py-12 md:py-16">
      <div className="container mx-auto px-4">
        <div className="max-w-4xl mx-auto text-center space-y-6 md:space-y-8">
          {/* Logo removed from footer since it's now in navigation */}
          
          <motion.p
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-gray-300 text-base md:text-lg"
          >
            Part of the Animal Writes Movement
          </motion.p>

          <div className="border-t border-gray-600 pt-6 md:pt-8">
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.2 }}
              viewport={{ once: true }}
              className="bg-[#FFF8F3] rounded-2xl p-6 md:p-8 mb-6"
            >
              <p className="text-[#333333] italic text-base md:text-lg leading-relaxed">
                "The greatness of a nation and its moral progress can be judged by the way its animals are treated."
              </p>
              <p className="text-[#666666] mt-3 text-sm md:text-base">— Mahatma Gandhi</p>
            </motion.div>

            <motion.p
              initial={{ opacity: 0 }}
              whileInView={{ opacity: 1 }}
              transition={{ duration: 0.6, delay: 0.3 }}
              viewport={{ once: true }}
              className="text-gray-400 text-sm mb-6 flex items-center justify-center gap-2"
            >
              <span>A peaceful nationwide movement of compassion and civic responsibility</span>
              <motion.div
                animate={{ scale: [1, 1.2, 1] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
              >
                <Heart className="w-4 h-4 text-[#D32F2F] inline" aria-hidden="true" />
              </motion.div>
            </motion.p>

            {/* Wugweb backlink */}
            <motion.div
              initial={{ opacity: 0 }}
              whileInView={{ opacity: 1 }}
              transition={{ duration: 0.6, delay: 0.4 }}
              viewport={{ once: true }}
              className="text-gray-500 text-xs pt-4 border-t border-gray-700"
            >
              <a 
                href="https://wugweb.com" 
                target="_blank" 
                rel="noopener noreferrer"
                className="hover:text-[#D32F2F] transition-colors focus:outline-none focus:ring-2 focus:ring-[#D32F2F] rounded px-2 py-1"
                aria-label="Visit wugweb website"
              >
                Created by wugweb
              </a>
            </motion.div>
          </div>
        </div>
      </div>
    </footer>
  );
}