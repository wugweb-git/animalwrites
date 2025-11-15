import { Share2, Twitter, Facebook, MessageCircle, Copy, Check, Users } from "lucide-react";
import { Button } from "./ui/button";
import { useState } from "react";
import { motion, AnimatePresence } from "motion/react";

export function SocialShare() {
  const [copied, setCopied] = useState(false);

  const shareText = "Join India's largest letter petition campaign for street dogs! Write to the Supreme Court on 29 Nov 2025. Every voice matters. #SaveIndianStreetDogs #AnimalWrites";
  const shareUrl = typeof window !== "undefined" ? window.location.href : "";

  const copyToClipboard = async () => {
    try {
      await navigator.clipboard.writeText(`${shareText}\n${shareUrl}`);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error("Failed to copy:", err);
    }
  };

  const shareOnTwitter = () => {
    const url = `https://twitter.com/intent/tweet?text=${encodeURIComponent(shareText)}&url=${encodeURIComponent(shareUrl)}`;
    window.open(url, "_blank");
  };

  const shareOnFacebook = () => {
    const url = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`;
    window.open(url, "_blank");
  };

  const shareOnWhatsApp = () => {
    const url = `https://wa.me/?text=${encodeURIComponent(shareText + " " + shareUrl)}`;
    window.open(url, "_blank");
  };

  return (
    <section 
      className="py-16 md:py-24 bg-gradient-to-b from-white to-[#F5F5F5]"
      aria-labelledby="social-share-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
          {/* Spread the Word Section */}
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="bg-white rounded-3xl p-8 md:p-12 shadow-lg border-2 border-gray-100 flex flex-col"
          >
            <div className="text-center mb-8 md:mb-10">
              <div className="inline-flex items-center gap-3 mb-4">
                <motion.div
                  animate={{ rotate: [0, 15, -15, 0] }}
                  transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                >
                  <Share2 className="w-8 h-8 text-[#F59E0B]" aria-hidden="true" />
                </motion.div>
                <h2 id="social-share-heading" className="text-2xl md:text-4xl text-[#333333]">Spread the Word</h2>
              </div>
              <p className="text-[#666666] text-base md:text-lg">
                Help us reach every corner of India. Share this campaign with your friends, family, and community.
              </p>
            </div>

            <div className="flex flex-wrap justify-center gap-3 md:gap-4 mb-6 md:mb-8">
              <motion.div
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                <Button
                  onClick={shareOnTwitter}
                  className="bg-[#1DA1F2] hover:bg-[#1a8cd8] text-white flex items-center gap-2 px-4 md:px-6 py-5 md:py-6 focus:ring-2 focus:ring-[#1DA1F2] focus:ring-offset-2"
                  aria-label="Share on Twitter"
                >
                  <Twitter className="w-5 h-5" aria-hidden="true" />
                  <span className="hidden sm:inline">Share on</span> Twitter
                </Button>
              </motion.div>

              <motion.div
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                <Button
                  onClick={shareOnFacebook}
                  className="bg-[#1877F2] hover:bg-[#166fe5] text-white flex items-center gap-2 px-4 md:px-6 py-5 md:py-6 focus:ring-2 focus:ring-[#1877F2] focus:ring-offset-2"
                  aria-label="Share on Facebook"
                >
                  <Facebook className="w-5 h-5" aria-hidden="true" />
                  <span className="hidden sm:inline">Share on</span> Facebook
                </Button>
              </motion.div>

              <motion.div
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                <Button
                  onClick={shareOnWhatsApp}
                  className="bg-[#25D366] hover:bg-[#20bd5a] text-white flex items-center gap-2 px-4 md:px-6 py-5 md:py-6 focus:ring-2 focus:ring-[#25D366] focus:ring-offset-2"
                  aria-label="Share on WhatsApp"
                >
                  <MessageCircle className="w-5 h-5" aria-hidden="true" />
                  <span className="hidden sm:inline">Share on</span> WhatsApp
                </Button>
              </motion.div>

              <motion.div
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
              >
                <Button
                  onClick={copyToClipboard}
                  variant="outline"
                  className="border-2 border-[#333333] text-[#333333] hover:bg-[#333333] hover:text-white flex items-center gap-2 px-4 md:px-6 py-5 md:py-6 focus:ring-2 focus:ring-[#333333] focus:ring-offset-2"
                  aria-label={copied ? "Link copied" : "Copy link"}
                >
                  <AnimatePresence mode="wait">
                    {copied ? (
                      <motion.div
                        key="check"
                        initial={{ scale: 0, rotate: -180 }}
                        animate={{ scale: 1, rotate: 0 }}
                        exit={{ scale: 0, rotate: 180 }}
                        className="flex items-center gap-2"
                      >
                        <Check className="w-5 h-5" aria-hidden="true" />
                        Copied!
                      </motion.div>
                    ) : (
                      <motion.div
                        key="copy"
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        exit={{ scale: 0 }}
                        className="flex items-center gap-2"
                      >
                        <Copy className="w-5 h-5" aria-hidden="true" />
                        Copy Link
                      </motion.div>
                    )}
                  </AnimatePresence>
                </Button>
              </motion.div>
            </div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.3 }}
              viewport={{ once: true }}
              className="bg-[#FFF8F3] rounded-2xl p-5 md:p-6 border-2 border-[#F59E0B]/20 mt-auto"
            >
              <p className="text-center text-[#333333] text-sm md:text-base">
                <strong>Tag your posts:</strong>{" "}
                <span className="text-[#F59E0B]">#SaveIndianStreetDogs</span> •{" "}
                <span className="text-[#F59E0B]">#NoDogsNoVotes</span> •{" "}
                <span className="text-[#F59E0B]">#AnimalWrites</span>
              </p>
            </motion.div>
          </motion.div>

          {/* Join the Movement Section */}
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            viewport={{ once: true }}
            className="bg-gradient-to-br from-[#333333] to-[#1a1a1a] rounded-3xl p-8 md:p-12 shadow-2xl text-center flex flex-col justify-center"
          >
            <div className="inline-flex items-center justify-center gap-3 mb-4">
              <motion.div
                animate={{ scale: [1, 1.1, 1] }}
                transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
              >
                <Users className="w-8 h-8 text-white" aria-hidden="true" />
              </motion.div>
              <h2 className="text-white text-2xl md:text-4xl">
                Join the Movement
              </h2>
            </div>
            <p className="text-white/90 text-base md:text-lg mb-6 md:mb-8 max-w-2xl mx-auto">
              Follow the official campaign page for updates, resources, and to connect with thousands of compassionate citizens across India
            </p>
            <motion.div
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
            >
              <Button
                asChild
                size="lg"
                className="bg-white text-[#333333] hover:bg-white/90 text-base md:text-lg px-6 md:px-8 py-5 md:py-6 h-auto focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-[#333333]"
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