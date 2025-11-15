import { FileText, MapPin, Send, Users } from "lucide-react";
import { motion } from "motion/react";
import { Button } from "./ui/button";
import { ImageWithFallback } from "./figma/ImageWithFallback";

export function HowToJoin() {
  const scrollToSection = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: "smooth" });
    }
  };

  const steps = [
    {
      number: "1",
      icon: FileText,
      title: "Write Your Letter",
      description: "Address it to the Chief Justice & Supreme Court. Share your voice, hope, reasoning.",
    },
    {
      number: "2",
      icon: MapPin,
      title: "Post It on 29 Nov",
      description: "Walk to your nearest GPO. Drop your letter. Stand with thousands.",
    },
    {
      number: "3",
      icon: Send,
      title: "Be Part of History",
      description: "Your letter joins India's largest coordinated letter-petition movement.",
    },
  ];

  return (
    <section 
      id="how-to-join" 
      className="py-16 md:py-24 bg-white"
      aria-labelledby="how-to-join-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12 md:mb-16"
          >
            <h2 id="how-to-join-heading" className="text-3xl md:text-5xl text-[#333333] mb-4 md:mb-6 font-bold">
              How You Can Join
            </h2>
            <p className="text-lg md:text-xl text-[#666666] max-w-2xl mx-auto">
              Three simple steps to make your voice heard
            </p>
          </motion.div>

          <div className="space-y-6 md:space-y-8 mb-12 md:mb-16">
            {steps.map((step, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, x: -30 }}
                whileInView={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.15, duration: 0.5 }}
                viewport={{ once: true }}
              >
                <motion.div
                  className="bg-[#F5F5F5] rounded-2xl p-6 md:p-10 hover:shadow-lg transition-shadow cursor-pointer"
                  whileHover={{ scale: 1.02, backgroundColor: "#FFF8F3" }}
                  transition={{ type: "spring", stiffness: 300 }}
                >
                  <div className="flex flex-col sm:flex-row items-start gap-4 md:gap-6">
                    <div className="flex-shrink-0 mx-auto sm:mx-0">
                      <motion.div
                        className="w-16 h-16 md:w-20 md:h-20 rounded-full bg-[#333333] text-white flex items-center justify-center text-2xl md:text-4xl shadow-lg"
                        whileHover={{ 
                          rotate: [0, -10, 10, -10, 0],
                          scale: 1.1 
                        }}
                        transition={{ duration: 0.5 }}
                      >
                        {step.number}
                      </motion.div>
                    </div>
                    <div className="flex-1 text-center sm:text-left">
                      <div className="flex flex-col sm:flex-row items-center sm:items-start gap-2 sm:gap-3 mb-3">
                        <motion.div
                          whileHover={{ scale: 1.2, rotate: 10 }}
                          transition={{ type: "spring", stiffness: 400 }}
                        >
                          <step.icon className="w-6 h-6 text-[#333333]" aria-hidden="true" />
                        </motion.div>
                        <h3 className="text-xl md:text-3xl text-[#333333]">{step.title}</h3>
                      </div>
                      <p className="text-base md:text-lg text-[#666666] leading-relaxed">{step.description}</p>
                    </div>
                  </div>
                </motion.div>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            viewport={{ once: true }}
            className="bg-[#FFF8F3] rounded-2xl p-6 md:p-12 border-2 border-[#333333]/20 hover:border-[#333333]/40 transition-colors"
          >
            <div className="flex flex-col md:flex-row items-center gap-6 md:gap-8">
              <motion.div
                className="flex-shrink-0"
                whileHover={{ rotate: 360, scale: 1.1 }}
                transition={{ duration: 0.6 }}
              >
                <div className="bg-white rounded-full p-4 md:p-6 shadow-lg">
                  <Users className="w-10 h-10 md:w-12 md:h-12 text-[#333333]" aria-hidden="true" />
                </div>
              </motion.div>
              <div className="flex-1 text-center md:text-left">
                <h3 className="text-xl md:text-2xl text-[#333333] mb-2">Want to Coordinate a Group?</h3>
                <p className="text-sm md:text-base text-[#666666] mb-4 md:mb-0">
                  Lead the movement in your city and inspire others to participate
                </p>
              </div>
              <motion.div
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                <Button
                  onClick={() => scrollToSection("volunteer")}
                  className="bg-[#333333] hover:bg-[#1a1a1a] text-white px-6 md:px-8 py-5 md:py-6 text-base md:text-lg focus:ring-2 focus:ring-[#333333] focus:ring-offset-2"
                  aria-label="Navigate to volunteer signup section"
                >
                  Join Leadership Circle
                </Button>
              </motion.div>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}