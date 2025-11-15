import { Share2, Twitter, Facebook, MessageCircle, Copy, Check } from "lucide-react";
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
      className="py-16 md:py-24 bg-white"
      aria-labelledby="social-share-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="bg-[#F5F5F5] rounded-3xl p-8 md:p-14 shadow-lg"
          >
            <div className="text-center mb-8 md:mb-10">
              <div className="inline-flex items-center gap-3 mb-4">
                <motion.div
                  animate={{ rotate: [0, 15, -15, 0] }}
                  transition={{ duration: 2, repeat: Infinity, ease: "easeInOut" }}
                >
                  <Share2 className="w-8 h-8 text-[#D32F2F]" aria-hidden="true" />
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
                  className="border-2 border-[#D32F2F] text-[#D32F2F] hover:bg-[#D32F2F] hover:text-white flex items-center gap-2 px-4 md:px-6 py-5 md:py-6 focus:ring-2 focus:ring-[#D32F2F] focus:ring-offset-2"
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
              className="bg-white rounded-2xl p-5 md:p-6 border-2 border-[#D32F2F]/20"
            >
              <p className="text-center text-[#333333] text-sm md:text-base">
                <strong>Tag your posts:</strong>{" "}
                <span className="text-[#D32F2F]">#SaveIndianStreetDogs</span> •{" "}
                <span className="text-[#D32F2F]">#NoDogsNoVotes</span> •{" "}
                <span className="text-[#D32F2F]">#AnimalWrites</span>
              </p>
            </motion.div>
          </motion.div>
        </div>
      </div>
    </section>
  );
}