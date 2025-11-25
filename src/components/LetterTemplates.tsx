import { useState } from "react";
import { FileText, Copy, Check, Download, ChevronDown, ChevronUp } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "./ui/card";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "./ui/tabs";
import { motion, AnimatePresence } from "framer-motion";

export function LetterTemplates() {
  const [copiedIndex, setCopiedIndex] = useState<number | null>(null);
  const [expandedIndex, setExpandedIndex] = useState<number | null>(null);

  const templates = [
    {
      title: "English",
      tone: "Formal Legal Letter",
      content: `By Speed Post 
 To, The Hon'ble Chief Justice of India and His Companion Judges, Supreme Court of India, Tilak Marg, New Delhi – 110001
Subject: Letter Petition in Re: "India Writes for Animal Rights"
May it please Your Lordships and Ladyship,
I write as a concerned citizen regarding the recent orders of the Hon'ble Supreme Court concerning community animals, particularly the order dated 07.11.2025 passed in Suo Motu W.P. (Civil) No. 5/2025. It is respectfully submitted that the said order appears neither legally sound nor scientifically informed, and may not address the issues it seeks to resolve.
A matter of serious concern is that the order was passed without hearing the interventionists and their counsel, violating basic principles of natural justice. Regardless of differing views on the subject, such a process risks undermining public confidence in the Court as the guardian of justice. Moreover, the order conflicts with the ABC Rules, 2023 and binding constitutional bench judgments.
Scientifically and practically, the directions are unworkable given the lack of funds and infrastructure, and may reverse the slow but steady progress achieved by animal caregivers despite institutional indifference. The intervenors have already highlighted several scientific, legal, and logical shortcomings in the current approach. Instead of fostering unity in addressing the issue, the order risks creating an avoidable divide between human and animal interests, when compassionate coexistence is both possible and necessary. Importantly, sustainable, humane, and legally sound solutions have already been placed before the Court but remain unconsidered.
In view of the above, it is prayed that the operation of the order dated 07.11.2025 be stayed and the matter be heard afresh by a constitutional bench.
Respectfully,
Name: Address: Phone: Email:`,
    },
    {
      title: "Hindi",
      tone: "औपचारिक कानूनी पत्र",
      content: `द्वारा स्पीड पोस्ट
सेवा में, माननीय भारत के मुख्य न्यायाधीश महोदय एवं उनके सहयोगी न्यायमूर्ति, भारत का सर्वोच्च न्यायालय, तिलक मार्ग, नई दिल्ली – 110001
विषय: "इंडिया राइट्स फॉर एनिमल राइट्स" विषयक पत्र याचिका
मान्यवर न्यायाधीशगण,
मैं एक चिंतित नागरिक के रूप में यह पत्र लिख रहा/रही हूँ, जो सामुदायिक पशुओं से संबंधित माननीय सर्वोच्च न्यायालय के हालिया आदेशों—विशेष रूप से दिनांक 07.11.2025 को पारित स्वतः संज्ञान वाद (सिविल) संख्या 5/2025—के संदर्भ में है। विनम्रतापूर्वक निवेदन है कि उपर्युक्त आदेश न तो विधिक रूप से सुदृढ़ प्रतीत होता है, न वैज्ञानिक दृष्टि से उचित है, तथा न ही यह संबंधित समस्या के समाधान में सहायक हो सकेगा।
गंभीर चिंता का विषय यह है कि यह आदेश हस्तक्षेपकर्ताओं एवं उनके अधिवक्ताओं को सुने बिना पारित किया गया, जो प्राकृतिक न्याय के मूल सिद्धांतों के विपरीत है। विषय पर मतभेद होने के बावजूद इस प्रकार की प्रक्रिया न्यायालय के प्रति जनता के विश्वास को प्रभावित कर सकती है। इसके अतिरिक्त, यह आदेश एबीसी नियमावली, 2023 तथा संविधान पीठों के बाध्यकारी निर्णयों के भी प्रतिकूल है।
वैज्ञानिक एवं व्यावहारिक दृष्टि से भी ये दिशा-निर्देश अव्यावहारिक हैं, क्योंकि उपलब्ध संसाधन, ढांचा एवं वित्तीय क्षमता अत्यंत सीमित हैं। इससे उन पशु-सेव��ों के वर्षों के अथक प्रयासों को भी क्षति पहुँच सकती है जिन्होंने संस्थागत उदासीनता के बावजूद सुधार लाने का कार्य किया है। हस्तक्षेपकर्ताओं द्वारा प्रस्तुत अनेक वैज्ञानिक, विधिक एवं तर्कसंगत आपत्तियाँ भी विचाराधीन नहीं की गई हैं। यह आदेश देश को समाधान की दिशा में एकजुट करने के बजाय मनुष्यों एवं पशुओं के बीच अनावश्यक विभाजन उत्पन्न कर सकता है, जबकि करुणामय सह-अस्तित्व पूर्णतः संभव है। महत्वपूर्ण यह भी है कि टिकाऊ, मानवीय एवं विधिसम्मत समाधान पहले ही न्यायालय के समक्ष प्रस्तुत किए जा चुके हैं, जिन पर विचार किया जाना आवश्यक है।
उपरोक्त तथ्��ों के प्रकाश में प्रार्थना है कि दिनांक 07.11.2025 के आदेश के संचालन पर तत्क्षण स्थगन प्रदान किया जाए तथा विषय को संविधान पीठ के समक्ष पुनः सुनवाई हेतु रखा जाए।
सादर,
नाम: पता: फोन: ईमेल:`,
    },
    {
      title: "Handwritten Letter Format",
      tone: "For those who want to write their own content",
      content: `By Speed Post 
 To, The Hon'ble Chief Justice of India and His Companion Judges, Supreme Court of India, Tilak Marg, New Delhi – 110001
Subject: Letter Petition in Re: "India Writes for Animal Rights"

[Write Your Own Content]

Respectfully,
Name: Address: Phone: Email:`,
    },
  ];

  const copyToClipboard = async (text: string, index: number) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedIndex(index);
      setTimeout(() => setCopiedIndex(null), 2000);
    } catch (err) {
      console.error("Failed to copy text:", err);
    }
  };

  const getPreview = (content: string) => {
    const lines = content.split('\n');
    return lines.slice(0, 8).join('\n') + (lines.length > 8 ? '\n...' : '');
  };

  const talkingPoints = [
    "Emphasize that you are not against public safety, but seeking humane solutions",
    "Mention the lack of infrastructure (shelters, resources) for mass relocation",
    "Reference successful ABC (Animal Birth Control) programs in various states",
    "Highlight the role street dogs play in communities as guards and companions",
    "Cite Article 51A(g) of the Constitution regarding compassion for living creatures",
    "Suggest community-based solutions and public awareness campaigns",
    "Request the Court to consider expert opinions from animal welfare organizations",
    "Emphasize coexistence over elimination as the Indian way of life",
  ];

  return (
    <section id="letter-templates" className="py-16 md:py-24 bg-[#F5F5F5]">
      <div className="container mx-auto px-4">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-16">
            <div className="inline-flex items-center gap-3 mb-4">
              <FileText className="w-8 h-8 text-[#333333]" />
              <h2 className="text-4xl md:text-5xl text-[#333333]">Letter Templates & Guidance</h2>
            </div>
            <p className="text-xl text-[#666666] max-w-3xl mx-auto">
              Use these templates as inspiration or starting points. Feel free to personalize them with your own experiences and thoughts.
            </p>
          </div>

          <Tabs defaultValue="templates" className="mb-12">
            <TabsList className="grid w-full grid-cols-2 max-w-md mx-auto mb-8 bg-white">
              <TabsTrigger value="templates" className="data-[state=active]:bg-[#333333] data-[state=active]:text-white">Templates</TabsTrigger>
              <TabsTrigger value="tips" className="data-[state=active]:bg-[#333333] data-[state=active]:text-white">Talking Points</TabsTrigger>
            </TabsList>

            <TabsContent value="templates">
              <div className="grid md:grid-cols-1 gap-6">
                {templates.map((template, index) => {
                  const isExpanded = expandedIndex === index;
                  const displayContent = isExpanded ? template.content : getPreview(template.content);
                  
                  return (
                    <Card key={index} className="border-2 border-gray-200 hover:border-[#F59E0B] transition-colors shadow-lg">
                      <CardHeader>
                        <div className="flex items-start justify-between gap-4">
                          <div>
                            <CardTitle className="text-[#333333] text-2xl font-bold">{template.title}</CardTitle>
                            <CardDescription className="text-[#666666] text-base">
                              Tone: {template.tone}
                            </CardDescription>
                          </div>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => copyToClipboard(template.content, index)}
                            className="flex items-center gap-2 border-[#333333] text-[#333333] hover:bg-[#333333] hover:text-white flex-shrink-0"
                            aria-label={`Copy ${template.title} template`}
                          >
                            {copiedIndex === index ? (
                              <>
                                <Check className="w-4 h-4" aria-hidden="true" />
                                Copied!
                              </>
                            ) : (
                              <>
                                <Copy className="w-4 h-4" aria-hidden="true" />
                                Copy
                              </>
                            )}
                          </Button>
                        </div>
                      </CardHeader>
                      <CardContent>
                        <div className="bg-white rounded-lg p-6 border border-gray-200 relative">
                          <AnimatePresence mode="wait">
                            <motion.pre
                              key={isExpanded ? 'expanded' : 'collapsed'}
                              initial={{ opacity: 0 }}
                              animate={{ opacity: 1 }}
                              exit={{ opacity: 0 }}
                              transition={{ duration: 0.2 }}
                              className="whitespace-pre-wrap text-sm text-[#333333] font-sans leading-relaxed"
                            >
                              {displayContent}
                            </motion.pre>
                          </AnimatePresence>
                          
                          {!isExpanded && (
                            <div className="absolute bottom-0 left-0 right-0 h-20 bg-gradient-to-t from-white to-transparent pointer-events-none" />
                          )}
                        </div>
                        
                        <motion.div
                          className="mt-4 flex justify-center"
                          whileHover={{ scale: 1.05 }}
                          whileTap={{ scale: 0.95 }}
                        >
                          <Button
                            variant="ghost"
                            onClick={() => setExpandedIndex(isExpanded ? null : index)}
                            className="text-[#F59E0B] hover:text-[#D97706] hover:bg-[#FFF8F3] flex items-center gap-2"
                            aria-expanded={isExpanded}
                            aria-label={isExpanded ? `Collapse ${template.title}` : `Read full ${template.title}`}
                          >
                            {isExpanded ? (
                              <>
                                <ChevronUp className="w-4 h-4" aria-hidden="true" />
                                Show Less
                              </>
                            ) : (
                              <>
                                <ChevronDown className="w-4 h-4" aria-hidden="true" />
                                Read Full Template
                              </>
                            )}
                          </Button>
                        </motion.div>
                      </CardContent>
                    </Card>
                  );
                })}
              </div>
            </TabsContent>

            <TabsContent value="tips">
              <Card className="border-2 border-[#F59E0B] shadow-lg">
                <CardHeader>
                  <CardTitle className="text-[#333333] text-2xl">Key Points to Include in Your Letter</CardTitle>
                  <CardDescription className="text-[#666666]">
                    These talking points can help you structure your thoughts effectively
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="space-y-4">
                    {talkingPoints.map((point, index) => (
                      <div key={index} className="flex items-start gap-4 p-4 bg-[#FFF8F3] rounded-lg border border-[#F59E0B]/20">
                        <div className="flex-shrink-0 w-8 h-8 bg-[#333333] text-white rounded-full flex items-center justify-center">
                          {index + 1}
                        </div>
                        <p className="text-[#333333] pt-1">{point}</p>
                      </div>
                    ))}
                  </div>

                  <div className="mt-8 p-6 bg-white rounded-lg border-2 border-gray-200">
                    <p className="text-[#333333] italic">
                      <strong>Remember:</strong> Your personal voice matters most. These are guidelines, not rules. 
                      Write from your heart, share your experiences, and express your genuine concern for both 
                      public safety and animal welfare.
                    </p>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>
          </Tabs>

        </div>
      </div>
    </section>
  );
}