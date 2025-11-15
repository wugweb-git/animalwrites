import { motion } from "motion/react";
import { MapPin } from "lucide-react";

export function IndiaMap() {
  const participatingStates = [
    { name: "Maharashtra", x: 35, y: 55, count: "2,500+" },
    { name: "Karnataka", x: 32, y: 65, count: "1,800+" },
    { name: "Delhi", x: 42, y: 25, count: "1,200+" },
    { name: "Tamil Nadu", x: 40, y: 75, count: "1,500+" },
    { name: "Gujarat", x: 25, y: 40, count: "1,100+" },
    { name: "West Bengal", x: 70, y: 35, count: "950+" },
    { name: "Rajasthan", x: 30, y: 30, count: "800+" },
    { name: "Kerala", x: 35, y: 80, count: "1,300+" },
    { name: "Telangana", x: 42, y: 60, count: "700+" },
    { name: "Uttar Pradesh", x: 50, y: 30, count: "1,600+" },
  ];

  return (
    <section className="py-16 md:py-24 bg-gradient-to-b from-white to-[#FFF8F3]" aria-labelledby="map-heading">
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <h2 id="map-heading" className="text-2xl md:text-4xl text-[#333333] mb-4">
              Movement Across India
            </h2>
            <p className="text-[#666666] text-base md:text-lg">
              Citizens from every corner of the nation are joining the cause
            </p>
          </motion.div>

          <div className="relative bg-white rounded-3xl p-8 md:p-12 shadow-xl border-2 border-[#D32F2F]/20">
            {/* Simplified India outline SVG */}
            <div className="relative w-full aspect-[3/4] max-w-md mx-auto">
              <svg
                viewBox="0 0 100 120"
                className="w-full h-full"
                aria-label="Map of India showing participating states"
              >
                {/* Simplified India shape */}
                <motion.path
                  d="M 50 10 L 60 15 L 70 20 L 75 30 L 80 40 L 75 50 L 70 60 L 65 70 L 60 80 L 55 90 L 50 100 L 45 95 L 40 85 L 35 75 L 30 65 L 25 55 L 20 45 L 25 35 L 30 25 L 40 15 Z"
                  fill="#FFF8F3"
                  stroke="#D32F2F"
                  strokeWidth="0.5"
                  initial={{ pathLength: 0 }}
                  whileInView={{ pathLength: 1 }}
                  transition={{ duration: 2, ease: "easeInOut" }}
                  viewport={{ once: true }}
                />

                {/* Participating state markers */}
                {participatingStates.map((state, index) => (
                  <motion.g
                    key={state.name}
                    initial={{ scale: 0, opacity: 0 }}
                    whileInView={{ scale: 1, opacity: 1 }}
                    transition={{ delay: 0.5 + index * 0.1, duration: 0.5 }}
                    viewport={{ once: true }}
                  >
                    <motion.circle
                      cx={state.x}
                      cy={state.y}
                      r="2"
                      fill="#D32F2F"
                      animate={{
                        scale: [1, 1.3, 1],
                        opacity: [0.8, 1, 0.8]
                      }}
                      transition={{
                        duration: 2,
                        repeat: Infinity,
                        delay: index * 0.2
                      }}
                    />
                    <motion.circle
                      cx={state.x}
                      cy={state.y}
                      r="3"
                      fill="none"
                      stroke="#D32F2F"
                      strokeWidth="0.3"
                      animate={{
                        r: [3, 6, 3],
                        opacity: [0.6, 0, 0.6]
                      }}
                      transition={{
                        duration: 2,
                        repeat: Infinity,
                        delay: index * 0.2
                      }}
                    />
                  </motion.g>
                ))}
              </svg>
            </div>

            {/* State labels */}
            <div className="mt-8 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 md:gap-4">
              {participatingStates.map((state, index) => (
                <motion.div
                  key={state.name}
                  initial={{ opacity: 0, y: 20 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  transition={{ delay: 1 + index * 0.05, duration: 0.4 }}
                  viewport={{ once: true }}
                  whileHover={{ scale: 1.05 }}
                  className="bg-[#FFF8F3] rounded-lg p-3 border border-[#D32F2F]/20 text-center"
                >
                  <MapPin className="w-4 h-4 text-[#D32F2F] mx-auto mb-1" aria-hidden="true" />
                  <p className="text-[#333333] text-xs md:text-sm font-medium">{state.name}</p>
                  <p className="text-[#D32F2F] text-xs">{state.count}</p>
                </motion.div>
              ))}
            </div>
          </div>

          <motion.div
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            transition={{ delay: 1.5, duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mt-8"
          >
            <p className="text-[#666666] text-sm md:text-base">
              <span className="text-[#D32F2F] font-medium">Growing every day!</span> More cities and states joining the movement.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}
