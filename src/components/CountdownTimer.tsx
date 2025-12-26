import { Vote, TrendingUp, Users, Target } from "lucide-react";
import { motion } from "motion/react";

export function CountdownTimer() {
  return (
    <section 
      className="py-12 md:py-16 bg-gradient-to-br from-primary via-primary/90 to-primary relative overflow-hidden"
      aria-labelledby="phase-two-heading"
    >
      {/* Animated background pattern */}
      <div className="absolute inset-0 opacity-10" aria-hidden="true">
        <div className="absolute top-0 left-0 w-full h-full">
          {[...Array(20)].map((_, i) => (
            <motion.div
              key={i}
              className="absolute w-2 h-2 bg-accent rounded-full"
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
          {/* Phase 2.0 Announcement */}
          <motion.div
            initial={{ opacity: 0, y: -20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="mb-8"
          >
            <motion.div
              className="inline-flex items-center gap-2 bg-accent rounded-[var(--radius-full)] px-5 md:px-7 py-2.5 md:py-3 mb-6"
              animate={{ 
                scale: [1, 1.05, 1],
              }}
              transition={{ 
                duration: 2, 
                repeat: Infinity, 
                ease: "easeInOut" 
              }}
            >
              <Target className="w-5 h-5 md:w-6 md:h-6 text-white" aria-hidden="true" />
              <span className="text-white uppercase tracking-wide">
                Phase 2.0 Now Live
              </span>
            </motion.div>
            
            <h2 id="phase-two-heading" className="text-white mb-4">
              Pashu Bachao, Vote Badhao
            </h2>
            <p className="text-accent mb-4 italic">
              (Save Animals, Increase Votes)
            </p>
            <p className="text-white/90 max-w-3xl mx-auto leading-relaxed">
              After 2.5 lakh letters to the Supreme Court, we're taking the next step. Politicians respond to votes. Let's show them that <span className="text-accent">animal welfare is electoral power</span>.
            </p>
          </motion.div>

          {/* What's Next Stats/Icons */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            viewport={{ once: true }}
            className="grid grid-cols-1 md:grid-cols-3 gap-5 md:gap-6 mt-8"
          >
            <motion.div
              className="bg-white/10 backdrop-blur-sm rounded-[var(--radius-xl)] p-5 md:p-7 border border-accent/30 hover:border-accent/60 transition-colors"
              whileHover={{ scale: 1.03, y: -5 }}
              transition={{ type: "spring", stiffness: 300 }}
            >
              <motion.div
                animate={{ rotate: [0, 5, -5, 0] }}
                transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
                className="inline-block mb-4"
              >
                <Vote className="w-10 h-10 md:w-12 md:h-12 text-accent" aria-hidden="true" />
              </motion.div>
              <h3 className="text-white mb-2">Register to Vote</h3>
              <p className="text-white/80 leading-relaxed">
                Ensure you're registered and ready to make animal welfare a voting issue
              </p>
            </motion.div>

            <motion.div
              className="bg-white/10 backdrop-blur-sm rounded-[var(--radius-xl)] p-5 md:p-7 border border-accent/30 hover:border-accent/60 transition-colors"
              whileHover={{ scale: 1.03, y: -5 }}
              transition={{ type: "spring", stiffness: 300 }}
            >
              <motion.div
                animate={{ scale: [1, 1.15, 1] }}
                transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
                className="inline-block mb-4"
              >
                <Users className="w-10 h-10 md:w-12 md:h-12 text-accent" aria-hidden="true" />
              </motion.div>
              <h3 className="text-white mb-2">Build the Movement</h3>
              <p className="text-white/80 leading-relaxed">
                Connect with fellow voters who care about animals and amplify our voice
              </p>
            </motion.div>

            <motion.div
              className="bg-white/10 backdrop-blur-sm rounded-[var(--radius-xl)] p-5 md:p-7 border border-accent/30 hover:border-accent/60 transition-colors"
              whileHover={{ scale: 1.03, y: -5 }}
              transition={{ type: "spring", stiffness: 300 }}
            >
              <motion.div
                animate={{ y: [0, -8, 0] }}
                transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
                className="inline-block mb-4"
              >
                <TrendingUp className="w-10 h-10 md:w-12 md:h-12 text-accent" aria-hidden="true" />
              </motion.div>
              <h3 className="text-white mb-2">Hold Leaders Accountable</h3>
              <p className="text-white/80 leading-relaxed">
                Make candidates pledge their commitment to animal welfare policies
              </p>
            </motion.div>
          </motion.div>

          {/* Call to Action */}
          <motion.div
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="mt-10 md:mt-12"
          >
            <p className="text-white/70 max-w-2xl mx-auto">
              Our collective voice sent 2.5 lakh letters. Now let's convert that compassion into electoral power and create lasting change for India's animals.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}