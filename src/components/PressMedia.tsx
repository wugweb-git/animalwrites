import { Newspaper, Radio, Tv } from "lucide-react";
import { motion } from "motion/react";

export function PressMedia() {
  return (
    <section
      className="py-16 md:py-20 bg-[#D32F2F]"
      id="press-media"
      aria-labelledby="press-media-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <div className="inline-flex items-center gap-3 mb-4">
              <motion.div
                animate={{ rotate: [0, 10, -10, 0] }}
                transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
              >
                <Newspaper className="w-7 h-7 md:w-8 md:h-8 text-white" aria-hidden="true" />
              </motion.div>
              <h2 id="press-media-heading" className="text-white text-2xl md:text-4xl">Media Coverage</h2>
            </div>
            <p className="text-white/90 text-base md:text-lg">
              India's largest citizen-led movement is making headlines
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 md:grid-cols-3 gap-4 md:gap-6">
            {[
              {
                icon: Newspaper,
                title: "Print Media",
                description: "Featured in major national and regional newspapers across India",
              },
              {
                icon: Tv,
                title: "Television",
                description: "Covered by leading news channels highlighting citizen participation",
              },
              {
                icon: Radio,
                title: "Social Media",
                description: "Trending nationwide with millions of impressions and growing support",
              },
            ].map((item, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, delay: index * 0.1 }}
                viewport={{ once: true }}
                whileHover={{ y: -8, scale: 1.02 }}
                className="bg-white/10 backdrop-blur-md rounded-2xl p-6 md:p-8 border-2 border-white/20 hover:bg-white/20 transition-all"
              >
                <motion.div
                  whileHover={{ rotate: 360 }}
                  transition={{ duration: 0.6 }}
                  className="flex justify-center"
                >
                  <item.icon className="w-10 h-10 md:w-12 md:h-12 text-white mb-4" aria-hidden="true" />
                </motion.div>
                <p className="text-white text-center text-lg md:text-xl mb-3">{item.title}</p>
                <p className="text-white/80 text-center text-sm md:text-base">{item.description}</p>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            viewport={{ once: true }}
            className="mt-10 md:mt-12 text-center"
          >
            <div className="inline-flex flex-wrap justify-center gap-3 md:gap-4 text-white/80">
              {[
                "#SaveIndianStreetDogs",
                "#NoDogsNoVotes",
                "#AnimalWrites",
                "#CoexistenceNotCruelty"
              ].map((hashtag, index) => (
                <motion.span
                  key={index}
                  className="bg-white/10 px-3 md:px-4 py-2 rounded-full text-sm md:text-base"
                  whileHover={{ scale: 1.1, backgroundColor: "rgba(255,255,255,0.2)" }}
                  transition={{ type: "spring", stiffness: 400 }}
                >
                  {hashtag}
                </motion.span>
              ))}
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}