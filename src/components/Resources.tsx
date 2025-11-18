import { Download, ExternalLink, FileText, Image as ImageIcon, Share2 } from "lucide-react";
import { Button } from "./ui/button";
import { motion } from "motion/react";

export function Resources() {
  const resources = [
    {
      icon: FileText,
      title: "Downloadable Posters",
      description: "Print-ready posters in English and regional languages to spread awareness",
      action: "Download ZIP",
    },
    {
      icon: ImageIcon,
      title: "Social Media Graphics",
      description: "Ready-to-share graphics for Facebook, Instagram, Twitter, and WhatsApp",
      action: "Download Pack",
    },
    {
      icon: FileText,
      title: "Sample Letters (PDF)",
      description: "Downloadable PDF versions of letter templates in multiple languages",
      action: "Download PDF",
    },
    {
      icon: Share2,
      title: "WhatsApp Forwards",
      description: "Pre-written messages to share with family, friends, and community groups",
      action: "Copy Messages",
    },
  ];

  const usefulLinks = [
    {
      title: "Animal Birth Control Rules, 2001",
      description: "Official government regulations on humane street dog management",
      link: "#",
    },
    {
      title: "Prevention of Cruelty to Animals Act, 1960",
      description: "India's primary animal welfare legislation",
      link: "#",
    },
    {
      title: "Constitutional Article 51A(g)",
      description: "Fundamental duty to show compassion towards living creatures",
      link: "#",
    },
    {
      title: "Kerala ABC Success Story",
      description: "Case study on effective implementation of ABC programs",
      link: "#",
    },
  ];

  return (
    <section 
      className="py-16 md:py-24 bg-white hidden" 
      id="resources"
      aria-labelledby="resources-heading"
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
            <motion.div
              className="inline-flex items-center gap-2 bg-[#FFF8F3] px-4 py-2 rounded-full mb-4 border border-[#F59E0B]/20"
              whileHover={{ scale: 1.05 }}
              transition={{ type: "spring", stiffness: 400 }}
            >
              <Download className="w-5 h-5 text-[#333333]" aria-hidden="true" />
              <span className="text-[#333333]">Download & Share</span>
            </motion.div>
            <h2 id="resources-heading" className="text-2xl md:text-4xl text-[#333333] mb-4 font-bold">
              Campaign Resources
            </h2>
            <p className="text-[#666666] text-base md:text-lg max-w-2xl mx-auto">
              Everything you need to amplify the movement
            </p>
          </motion.div>

          <div className="grid sm:grid-cols-2 gap-4 md:gap-6 mb-12 md:mb-16">
            {resources.map((resource, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, delay: index * 0.1 }}
                viewport={{ once: true }}
              >
                <motion.div
                  className="bg-white rounded-2xl p-6 md:p-8 border-2 border-gray-200 hover:border-[#F59E0B] hover:shadow-lg transition-all h-full"
                  whileHover={{ y: -5 }}
                  transition={{ type: "spring", stiffness: 300 }}
                >
                  <div className="flex items-start gap-4 mb-4">
                    <motion.div
                      className="bg-[#FFF8F3] rounded-full p-3"
                      whileHover={{ rotate: 360, scale: 1.1 }}
                      transition={{ duration: 0.6 }}
                    >
                      <resource.icon className="w-6 h-6 text-[#333333]" aria-hidden="true" />
                    </motion.div>
                    <div className="flex-1">
                      <h3 className="text-[#333333] mb-2 text-base md:text-lg font-bold">{resource.title}</h3>
                      <p className="text-[#666666] text-sm mb-4">{resource.description}</p>
                      <motion.div
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.98 }}
                      >
                        <Button
                          variant="outline"
                          className="w-full border-2 border-[#333333] text-[#333333] hover:bg-[#333333] hover:text-white transition-colors"
                          aria-label={`${resource.action} - ${resource.title}`}
                        >
                          <Download className="w-4 h-4 mr-2" aria-hidden="true" />
                          {resource.action}
                        </Button>
                      </motion.div>
                    </div>
                  </div>
                </motion.div>
              </motion.div>
            ))}
          </div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            viewport={{ once: true }}
            className="bg-[#FFF8F3] rounded-2xl p-6 md:p-12 border-2 border-[#F59E0B]/20"
          >
            <h3 className="text-[#F59E0B] text-lg md:text-xl mb-6 text-center font-bold">Useful Legal & Reference Links</h3>
            <div className="grid sm:grid-cols-2 gap-4">
              {usefulLinks.map((link, index) => (
                <motion.a
                  key={index}
                  href={link.link}
                  initial={{ opacity: 0, scale: 0.95 }}
                  whileInView={{ opacity: 1, scale: 1 }}
                  transition={{ duration: 0.3, delay: index * 0.05 }}
                  viewport={{ once: true }}
                  whileHover={{ scale: 1.02, borderColor: "#F59E0B" }}
                  className="flex items-start gap-3 bg-white rounded-xl p-4 md:p-6 border-2 border-gray-200 hover:shadow-md transition-all"
                  aria-label={`Read more about ${link.title}`}
                >
                  <motion.div
                    whileHover={{ scale: 1.2, rotate: -15 }}
                    transition={{ duration: 0.3 }}
                  >
                    <ExternalLink className="w-5 h-5 text-[#333333] flex-shrink-0 mt-1" aria-hidden="true" />
                  </motion.div>
                  <div className="flex-1">
                    <p className="text-[#333333] mb-1 text-sm md:text-base">{link.title}</p>
                    <p className="text-xs md:text-sm text-[#666666]">{link.description}</p>
                  </div>
                </motion.a>
              ))}
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.5 }}
            viewport={{ once: true }}
            className="mt-6 md:mt-8 bg-white rounded-2xl p-5 md:p-6 border-2 border-[#F59E0B]/20"
          >
            <p className="text-center text-[#666666] text-sm md:text-base">
              <span className="text-[#F59E0B]">Need more resources?</span> Contact us on social media 
              using <span className="text-[#F59E0B] font-medium">#AnimalWrites</span> and we'll do our best to help.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}