import { Mail, Heart, Instagram, Facebook, Youtube, MapPin, Phone, ExternalLink } from "lucide-react";
import { motion } from "motion/react";
import { Logo } from "./Logo";
import { VisitorCounter } from "./VisitorCounter";

export function Footer() {
  const currentYear = new Date().getFullYear();
  
  return (
    <footer className="bg-gradient-to-b from-[#333333] to-[#222222] text-white relative overflow-hidden">
      {/* Decorative top wave */}
      <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-[#F59E0B] via-[#D97706] to-[#F59E0B]" aria-hidden="true" />
      
      {/* Animated background elements */}
      <div className="absolute inset-0 opacity-5" aria-hidden="true">
        {[...Array(15)].map((_, i) => (
          <motion.div
            key={i}
            className="absolute w-2 h-2 bg-[#F59E0B] rounded-full"
            style={{
              left: `${Math.random() * 100}%`,
              top: `${Math.random() * 100}%`,
            }}
            animate={{
              scale: [1, 1.5, 1],
              opacity: [0.2, 0.5, 0.2],
            }}
            transition={{
              duration: 4,
              repeat: Infinity,
              delay: i * 0.3,
              ease: "easeInOut",
            }}
          />
        ))}
      </div>

      <div className="container mx-auto px-4 md:px-6 lg:px-12 relative z-10">
        {/* Main Footer Content */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 md:gap-10 py-12 md:py-16">
          
          {/* Brand Section */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="lg:col-span-1"
          >
            <div className="flex flex-col items-start gap-4">
              <Logo 
                size={80}
                className="h-16 w-16 md:h-20 md:w-20"
              />
              <div>
                <h2 className="text-2xl md:text-3xl text-white mb-2">Animal Writes</h2>
                <p className="text-[#F59E0B] text-sm md:text-base mb-4">
                  India Writes for Animal Rights
                </p>
              </div>
              <p className="text-gray-300 text-sm leading-relaxed">
                A peaceful nationwide movement of compassion and civic responsibility to protect India's voiceless animals.
              </p>
              
              {/* Social Media Links */}
              <div className="flex items-center gap-3 mt-4">
                <a
                  href="https://www.instagram.com/animalwrites_india/"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-10 h-10 rounded-full bg-white/10 hover:bg-[#F59E0B] flex items-center justify-center transition-all duration-300 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:ring-offset-2 focus:ring-offset-[#333333]"
                  aria-label="Follow us on Instagram"
                >
                  <Instagram className="w-5 h-5" />
                </a>
                <a
                  href="https://x.com/AnimalWrites"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-10 h-10 rounded-full bg-white/10 hover:bg-[#F59E0B] flex items-center justify-center transition-all duration-300 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:ring-offset-2 focus:ring-offset-[#333333]"
                  aria-label="Follow us on X (formerly Twitter)"
                >
                  <svg className="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
                  </svg>
                </a>
                <a
                  href="https://www.facebook.com/AnimalWritesIndia/"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-10 h-10 rounded-full bg-white/10 hover:bg-[#F59E0B] flex items-center justify-center transition-all duration-300 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:ring-offset-2 focus:ring-offset-[#333333]"
                  aria-label="Follow us on Facebook"
                >
                  <Facebook className="w-5 h-5" />
                </a>
                <a
                  href="https://youtube.com"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="w-10 h-10 rounded-full bg-white/10 hover:bg-[#F59E0B] flex items-center justify-center transition-all duration-300 hover:scale-110 focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:ring-offset-2 focus:ring-offset-[#333333]"
                  aria-label="Subscribe on YouTube"
                >
                  <Youtube className="w-5 h-5" />
                </a>
              </div>
            </div>
          </motion.div>

          {/* Quick Links */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.1 }}
            viewport={{ once: true }}
          >
            <h3 className="text-[#F59E0B] text-lg mb-4">Quick Links</h3>
            <ul className="space-y-3">
              {[
                { label: "About the Movement", href: "#about" },
                { label: "Why This Matters", href: "#why" },
                { label: "How to Join", href: "#how-to-join" },
                { label: "Letter Templates", href: "#templates" },
                { label: "Upload Receipt", href: "#upload" },
                { label: "Volunteer", href: "#volunteer" },
              ].map((link, index) => (
                <li key={index}>
                  <a
                    href={link.href}
                    className="text-gray-300 hover:text-[#F59E0B] transition-colors duration-200 text-sm inline-flex items-center gap-2 group"
                  >
                    <span className="w-1.5 h-1.5 rounded-full bg-gray-600 group-hover:bg-[#F59E0B] transition-colors" aria-hidden="true" />
                    {link.label}
                  </a>
                </li>
              ))}
            </ul>
          </motion.div>

          {/* Campaign Info */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            viewport={{ once: true }}
          >
            <h3 className="text-[#F59E0B] text-lg mb-4">Campaign Phases</h3>
            <ul className="space-y-3">
              <li className="text-gray-300 text-sm flex items-start gap-2">
                <span className="text-[#F59E0B] mt-1">✓</span>
                <span><strong>Phase 1.0:</strong> 2.5 Lakh+ letters to Supreme Court</span>
              </li>
              <li className="text-gray-300 text-sm flex items-start gap-2">
                <span className="text-[#F59E0B] mt-1">→</span>
                <span><strong>Phase 2.0:</strong> Pashu Bachao, Vote Badhao</span>
              </li>
            </ul>
            
            <div className="mt-6 p-4 bg-white/5 rounded-lg border border-[#F59E0B]/20">
              <h4 className="text-white text-sm mb-2 flex items-center gap-2">
                <Heart className="w-4 h-4 text-[#F59E0B]" aria-hidden="true" />
                Our Mission
              </h4>
              <p className="text-gray-400 text-xs leading-relaxed">
                To create a compassionate India where animal welfare is a constitutional priority and electoral imperative.
              </p>
            </div>
          </motion.div>

          {/* Contact */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            viewport={{ once: true }}
          >
            <h3 className="text-[#F59E0B] text-lg mb-4">Get In Touch</h3>
            <ul className="space-y-4">
              <li className="flex items-start gap-3">
                <Mail className="w-5 h-5 text-[#F59E0B] mt-0.5 flex-shrink-0" aria-hidden="true" />
                <div>
                  <p className="text-xs text-gray-400 mb-1">Email us</p>
                  <a 
                    href="mailto:contact@animalwrites.in" 
                    className="text-gray-300 hover:text-[#F59E0B] transition-colors text-sm"
                  >
                    contact@animalwrites.in
                  </a>
                </div>
              </li>
              <li className="flex items-start gap-3">
                <MapPin className="w-5 h-5 text-[#F59E0B] mt-0.5 flex-shrink-0" aria-hidden="true" />
                <div>
                  <p className="text-xs text-gray-400 mb-1">Movement HQ</p>
                  <p className="text-gray-300 text-sm">New Delhi, India</p>
                </div>
              </li>
            </ul>

            <div className="mt-6 p-4 bg-[#F59E0B]/10 border border-[#F59E0B]/30 rounded-lg">
              <p className="text-[#F59E0B] text-xs mb-2 uppercase tracking-wider">Join the Movement</p>
              <a 
                href="#volunteer"
                className="inline-flex items-center gap-2 text-white hover:text-[#F59E0B] transition-colors text-sm group"
              >
                Become a Volunteer
                <ExternalLink className="w-4 h-4 group-hover:translate-x-1 transition-transform" aria-hidden="true" />
              </a>
            </div>
          </motion.div>
        </div>

        {/* Bottom Bar */}
        <motion.div
          initial={{ opacity: 0 }}
          whileInView={{ opacity: 1 }}
          transition={{ duration: 0.6, delay: 0.4 }}
          viewport={{ once: true }}
          className="border-t border-gray-700 pt-8 pb-8"
        >
          <div className="flex flex-col lg:flex-row items-center justify-between gap-6">
            {/* Copyright & Legal Links */}
            <div className="flex flex-col md:flex-row items-center gap-4 md:gap-6">
              <p className="text-gray-400 text-sm text-center md:text-left">
                © {currentYear} Animal Writes. All rights reserved. Made with{" "}
                <Heart className="w-4 h-4 inline text-[#F59E0B]" aria-hidden="true" /> for India's animals.
              </p>
              
              {/* Legal Links */}
              <div className="flex items-center gap-4 text-sm">
                <a 
                  href="#terms" 
                  className="text-gray-400 hover:text-[#F59E0B] transition-colors duration-200"
                >
                  Terms & Conditions
                </a>
                <span className="text-gray-600">|</span>
                <a 
                  href="#privacy" 
                  className="text-gray-400 hover:text-[#F59E0B] transition-colors duration-200"
                >
                  Privacy Policy
                </a>
              </div>
            </div>

            {/* Visitor Counter & Wugweb Credit */}
            <div className="flex flex-col md:flex-row items-center gap-4">
              {/* Visitor Counter */}
              <VisitorCounter />
              
              {/* Wugweb Credit */}
              <a 
                href="https://wugweb.com" 
                target="_blank" 
                rel="noopener noreferrer"
                className="inline-flex items-center gap-3 bg-[#333333] hover:bg-gradient-to-r hover:from-[#F59E0B] hover:to-[#D97706] text-white px-5 py-2.5 rounded-full transition-all duration-300 hover:shadow-lg hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#F59E0B] focus:ring-offset-2 focus:ring-offset-[#222222] group flex-shrink-0"
                aria-label="Visit wugweb website - Website created by wugweb"
              >
                <motion.img 
                  src="https://media.designrush.com/agencies/713808/conversions/Wugweb-logo-profile.jpg"
                  alt="Wugweb logo"
                  className="h-8 w-auto object-contain rounded"
                />
                <div className="text-left">
                  <div className="text-xs text-gray-300 group-hover:text-white transition-colors">Created by</div>
                  <div className="text-sm font-semibold">wugweb</div>
                </div>
              </a>
            </div>
          </div>
        </motion.div>
      </div>
    </footer>
  );
}