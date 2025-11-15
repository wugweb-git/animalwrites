import { HelpCircle } from "lucide-react";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "./ui/accordion";
import { motion } from "framer-motion";

export function FAQ() {
  const faqs = [
    {
      question: "Why handwritten letters? Can I send an email instead?",
      answer: "Handwritten letters create a powerful physical presence that cannot be ignored. A single email can be easily deleted, but thousands of physical letters arriving at the Supreme Court create an undeniable statement. The act of writing by hand also shows personal commitment and effort, making each letter more meaningful.",
    },
    {
      question: "What should I write in my letter?",
      answer: "Write from your heart! Share your concerns about the impracticality of mass relocation, emphasize the need for humane solutions like ABC (Animal Birth Control) programs, and express your hope for coexistence. Check our letter templates for inspiration, but personalizing your message makes it more powerful.",
    },
    {
      question: "Do I need to use a specific format or language?",
      answer: "You can write in English, Hindi, or any regional language you're comfortable with. While we provide templates, there's no strict format requirement. Just ensure your letter is respectful, clearly addressed to the Chief Justice of India and the Supreme Court, and expresses your concerns and suggestions.",
    },
    {
      question: "What if there's no GPO in my town?",
      answer: "You can mail your letter from any post office—it doesn't have to be a GPO. However, we encourage visiting GPOs on November 29th to create a visible, collective movement. If that's not possible, mailing from your local post office on that date is perfectly fine!",
    },
    {
      question: "Is this campaign legally safe? Will I face any issues?",
      answer: "Absolutely safe! Writing letters to the Supreme Court is a constitutional right under freedom of speech. This is a peaceful, legal, and democratic way to petition the judiciary. We are not protesting; we are respectfully requesting reconsideration through proper channels.",
    },
    {
      question: "How much will postage cost?",
      answer: "Sending a letter within India typically costs ₹5-10 depending on weight and speed of delivery. This small investment helps save millions of lives and ensures your voice reaches the highest court of the land.",
    },
    {
      question: "Can I send multiple letters?",
      answer: "Yes! You can send one letter representing your personal views, and encourage family members and friends to send their own letters too. However, each letter should be unique and sincere—mass-produced identical letters may be less effective.",
    },
    {
      question: "What happens after we send the letters?",
      answer: "The Supreme Court will receive thousands of letters from concerned citizens across India. This collective voice can influence the Court's consideration of the matter and demonstrate widespread public concern. We will also share updates on the campaign's impact through our social media channels.",
    },
    {
      question: "I love the cause but can't write a letter. How else can I help?",
      answer: "You can still make a difference! Share this campaign on social media, talk to friends and family about it, volunteer to help others write or mail their letters, or donate to animal welfare organizations working on ABC programs. Every bit of support counts!",
    },
    {
      question: "Is this campaign affiliated with any political party?",
      answer: "No. Animal Writes is a non-political, citizen-led movement focused solely on animal welfare and humane solutions. We welcome support from people of all political backgrounds who share our concern for India's street dogs.",
    },
  ];

  return (
    <section
      id="faq"
      className="py-16 md:py-24 bg-gradient-to-b from-white to-[#FFF8F3]"
      aria-labelledby="faq-heading"
    >
      <div className="container mx-auto px-4">
        <div className="max-w-4xl mx-auto">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6 }}
            viewport={{ once: true }}
            className="text-center mb-12"
          >
            <div className="inline-flex items-center gap-3 mb-4">
              <HelpCircle className="w-8 h-8 text-[#D32F2F]" aria-hidden="true" />
              <h2 id="faq-heading" className="text-3xl md:text-4xl text-[#333333]">
                Frequently Asked Questions
              </h2>
            </div>
            <p className="text-[#666666] text-lg">
              Got questions? We've got answers. Here's everything you need to know about the campaign.
            </p>
          </motion.div>

          <Accordion type="single" collapsible className="space-y-4">
            {faqs.map((faq, index) => (
              <motion.div
                key={index}
                initial={{ opacity: 0, y: 20 }}
                whileInView={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.4, delay: index * 0.05 }}
                viewport={{ once: true }}
              >
                <AccordionItem
                  value={`item-${index}`}
                  className="bg-white border-2 border-gray-200 rounded-xl px-6 hover:border-[#D32F2F] hover:shadow-md transition-all duration-300"
                >
                  <AccordionTrigger
                    className="text-left text-[#333333] hover:text-[#D32F2F] py-5"
                    aria-label={`Question ${index + 1}: ${faq.question}`}
                  >
                    <span className="pr-4">{faq.question}</span>
                  </AccordionTrigger>
                  <AccordionContent className="text-[#666666] leading-relaxed pb-5">
                    {faq.answer}
                  </AccordionContent>
                </AccordionItem>
              </motion.div>
            ))}
          </Accordion>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            viewport={{ once: true }}
            className="mt-12 bg-[#FFF8F3] rounded-2xl p-8 border-2 border-[#D32F2F]/20"
          >
            <p className="text-center text-[#333333] leading-relaxed">
              <strong className="text-[#D32F2F]">Still have questions?</strong> Feel free to reach out to us on social media
              using <span className="text-[#D32F2F] font-semibold">#AnimalWrites</span> or contact your local animal welfare organizations
              for more information.
            </p>
          </motion.div>
        </div>
      </div>
    </section>
  );
}