import { Calendar, Heart, Users, Mail } from "lucide-react";
import { motion } from "framer-motion";

export function CountdownTimer() {
  return (
    <section 
      className="py-8 md:py-12 bg-[#333333] relative overflow-hidden"
      aria-labelledby="campaign-day-heading"
    >
      {/* Animated background pattern */}
      <div className="absolute inset-0 opacity-10" aria-hidden="true">
        <div className="absolute top-0 left-0 w-full h-full">
          {[...Array(20)].map((_, i) => (
            <motion.div
              key={i}
              className="absolute w-2 h-2 bg-white rounded-full"
              style={{
                left: `${Math.random() * 100}%`,
                top: `${Math.random() * 100}%`,
              }}
              animate={{
                scale: [1, 1.5, 1],
                opacity: [0.3, 0.6, 0.3],
              }}
              transition={{
                duration: 3,
                repeat: Infinity,
                delay: i * 0.2,
                ease: "easeInOut",
              }}
            />
          ))}
        </div>
      </div>

      <div className="container mx-auto px-6 md:px-12 lg:px-16 relative z-10">
        <div className="max-w-5xl mx-auto text-center">
          {/* Main Announcement */}
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="mb-6"
          >
            <motion.div
              className="inline-flex items-center gap-2 bg-[#F59E0B] rounded-full px-4 md:px-6 py-2 md:py-3 mb-4"
              animate={{ 
                scale: [1, 1.05, 1],
              }}
              transition={{ 
                duration: 2, 
                repeat: Infinity, 
                ease: "easeInOut" 
              }}
            >
              <Calendar className="w-5 h-5 md:w-6 md:h-6 text-white" aria-hidden="true" />
              <span className="text-white text-sm md:text-base uppercase tracking-wide">
                Today is Campaign Day
              </span>
            </motion.div>
            
            <h2 id="campaign-day-heading" className="text-white text-3xl md:text-5xl mb-4">
              The Day Has Arrived
            </h2>
            <p className="text-white/90 text-base md:text-xl max-w-3xl mx-auto">
              Thousands of citizens across India are walking to their nearest GPO today to post handwritten letters to the Supreme Court. Join the movement.
            </p>
          </motion.div>

          {/* Action Stats/Icons */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            viewport={{ once: true }}
            className="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6"
          >
            <motion.div
              className="bg-white/10 backdrop-blur-sm rounded-lg p-4 md:p-6 border border-white/20"
              whileHover={{ scale: 1.05, backgroundColor: "rgba(255, 255, 255, 0.15)" }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <motion.div
                animate={{ rotate: [0, 10, -10, 0] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                className="inline-block mb-3"
              >
                <Mail className="w-8 h-8 md:w-10 md:h-10 text-[#F59E0B]" aria-hidden="true" />
              </motion.div>
              <h3 className="text-white text-lg md:text-xl mb-1">Write Your Letter</h3>
              <p className="text-white/80 text-sm md:text-base">Express your voice through words</p>
            </motion.div>

            <motion.div
              className="bg-white/10 backdrop-blur-sm rounded-lg p-4 md:p-6 border border-white/20"
              whileHover={{ scale: 1.05, backgroundColor: "rgba(255, 255, 255, 0.15)" }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <motion.div
                animate={{ scale: [1, 1.2, 1] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                className="inline-block mb-3"
              >
                <Users className="w-8 h-8 md:w-10 md:h-10 text-[#F59E0B]" aria-hidden="true" />
              </motion.div>
              <h3 className="text-white text-lg md:text-xl mb-1">Walk to Your GPO</h3>
              <p className="text-white/80 text-sm md:text-base">Join thousands across the nation</p>
            </motion.div>

            <motion.div
              className="bg-white/10 backdrop-blur-sm rounded-lg p-4 md:p-6 border border-white/20"
              whileHover={{ scale: 1.05, backgroundColor: "rgba(255, 255, 255, 0.15)" }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <motion.div
                animate={{ y: [0, -5, 0] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                className="inline-block mb-3"
              >
                <Heart className="w-8 h-8 md:w-10 md:h-10 text-[#F59E0B]" aria-hidden="true" />
              </motion.div>
              <h3 className="text-white text-lg md:text-xl mb-1">Make an Impact</h3>
              <p className="text-white/80 text-sm md:text-base">Every letter speaks for voiceless animals</p>
            </motion.div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
