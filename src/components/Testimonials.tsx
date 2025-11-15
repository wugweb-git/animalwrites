import { Quote } from "lucide-react";
import { Card, CardContent } from "./ui/card";
import { motion } from "motion/react";

export function Testimonials() {
  const testimonials = [
    {
      quote: "Street dogs are not strays—they are guardians of our neighborhoods. They deserve dignity, not displacement.",
      author: "Priya Sharma",
      location: "Mumbai, Maharashtra",
      role: "Animal Welfare Volunteer",
    },
    {
      quote: "As a parent, I understand safety concerns. But the solution is education and ABC programs, not mass relocation that's impossible to implement humanely.",
      author: "Rajesh Kumar",
      location: "Bangalore, Karnataka",
      role: "Father & Community Leader",
    },
    {
      quote: "Our Constitution teaches compassion for all living beings. This campaign is about upholding those values while ensuring public safety.",
      author: "Advocate Meera Nair",
      location: "Delhi",
      role: "Legal Professional",
    },
    {
      quote: "I've lived alongside street dogs my entire life. They protect us at night, warn us of danger. They're part of our community.",
      author: "Amit Patel",
      location: "Ahmedabad, Gujarat",
      role: "Shop Owner",
    },
    {
      quote: "Kerala's success with ABC programs proves there's a better way. Let's learn from what works rather than create a humanitarian crisis.",
      author: "Dr. Lakshmi Menon",
      location: "Kochi, Kerala",
      role: "Veterinarian",
    },
    {
      quote: "This is about more than dogs. It's about what kind of society we want to be—one that chooses compassion over convenience.",
      author: "Arjun Singh",
      location: "Jaipur, Rajasthan",
      role: "College Student",
    },
  ];

  return (
    <section 
      className="py-16 md:py-24 bg-gradient-to-b from-white to-[#FFF8F3]"
      aria-labelledby="testimonials-heading"
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
            <h2 id="testimonials-heading" className="text-[#D32F2F] text-2xl md:text-4xl mb-4">Voices From Across India</h2>
            <p className="text-[#666666] text-base md:text-lg">
              Real people, real stories, one united movement
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
            {testimonials.map((testimonial, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 30 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.5, delay: index * 0.1 }}
                viewport={{ once: true }}
              >
                <motion.div
                  whileHover={{ y: -8, boxShadow: "0 20px 30px -10px rgba(0,0,0,0.15)" }}
                  transition={{ type: "spring", stiffness: 300 }}
                >
                  <Card className="border-2 border-[#D32F2F]/20 hover:border-[#D32F2F] transition-colors h-full">
                    <CardContent className="p-5 md:p-6 flex flex-col h-full">
                      <motion.div
                        whileHover={{ rotate: 180, scale: 1.2 }}
                        transition={{ duration: 0.6 }}
                      >
                        <Quote className="w-7 h-7 md:w-8 md:h-8 text-[#D32F2F] mb-4 opacity-50" aria-hidden="true" />
                      </motion.div>
                      <p className="text-[#666666] italic mb-6 flex-grow text-sm md:text-base leading-relaxed">
                        "{testimonial.quote}"
                      </p>
                      <div className="border-t-2 border-[#D32F2F]/10 pt-4">
                        <p className="text-[#333333] font-medium text-sm md:text-base">{testimonial.author}</p>
                        <p className="text-xs md:text-sm text-[#666666]">{testimonial.location}</p>
                        <p className="text-xs text-[#D32F2F] mt-1">{testimonial.role}</p>
                      </div>
                    </CardContent>
                  </Card>
                </motion.div>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.6 }}
            viewport={{ once: true }}
            className="mt-10 md:mt-12 text-center"
          >
            <div className="inline-block bg-gradient-to-r from-[#FFF8F3] to-white rounded-xl p-6 md:p-8 border-2 border-[#D32F2F]/30">
              <p className="text-[#333333] text-base md:text-lg">
                <strong className="text-[#D32F2F]">Your voice matters too.</strong> Add it to thousands of others. 
                Write your letter today.
              </p>
            </div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}