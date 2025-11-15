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
      title: "Compassionate Citizen",
      tone: "Personal & Emotional",
      content: `To,
The Chief Justice of India
The Hon'ble Supreme Court of India
Tilak Marg, New Delhi - 110001

Respected Sir/Madam,

Subject: Urgent Reconsideration of Street Dog Relocation Order

I write to you as a concerned citizen who believes deeply in the values of compassion and coexistence that define our nation.

The recent order calling for the removal or relocation of stray dogs, while well-intentioned for public safety, is not practically executable. India lacks the infrastructure and government-run shelters to house millions of street dogs humanely.

These animals are not merely strays—they are guardians of our neighborhoods, companions to many, and sentient beings deserving of dignity. Forced relocation will cause immense suffering to both animals and communities.

I humbly urge the Hon'ble Court to reconsider this directive and explore humane alternatives such as:
• Strengthened ABC (Animal Birth Control) programs
• Community-led care initiatives  
• Public awareness campaigns for peaceful coexistence

Justice, I believe, must be compassionate. India has always been a land that respects all life.

With deepest respect,
[Your Name]
[Your City]
[Date]`,
    },
    {
      title: "Practical Approach",
      tone: "Logical & Solution-Focused",
      content: `To,
The Chief Justice of India
The Hon'ble Supreme Court of India
Tilak Marg, New Delhi - 110001

Respected Sir/Madam,

Subject: Request for Practical Review of Street Dog Management Order

I am writing to bring to your attention the practical challenges in implementing the recent order regarding stray dog relocation.

Ground Realities:
1. India has an estimated 60+ million stray dogs with no infrastructure to house them
2. Relocation without proper facilities will lead to mass suffering and public health crises
3. The current directive lacks a clear, humane, and legally sound implementation pathway

Evidence-Based Solutions:
• ABC (Animal Birth Control) programs have proven effective in Kerala and other states
• Community stewardship models reduce conflict and improve public safety
• Scientific sterilization is more cost-effective than relocation

I respectfully urge the Hon'ble Court to consider a revised approach that balances public safety with animal welfare, backed by scientific evidence and practical feasibility.

Your consideration of this matter would serve both justice and humanity.

Respectfully submitted,
[Your Name]
[Your City]
[Date]`,
    },
    {
      title: "Legal Perspective",
      tone: "Constitutional & Rights-Based",
      content: `To,
The Chief Justice of India
The Hon'ble Supreme Court of India
Tilak Marg, New Delhi - 110001

Respected Sir/Madam,

Subject: Constitutional Concerns Regarding Street Dog Relocation Directive

I write as a citizen deeply committed to constitutional values and the rule of law.

Article 51A(g) of our Constitution mandates compassion towards all living creatures. The Prevention of Cruelty to Animals Act, 1960, and the Animal Birth Control Rules, 2001, provide a legal framework for humane street dog management.

The recent relocation order, while addressing public safety concerns, may inadvertently conflict with:
• Existing animal welfare legislation
• Practical implementation capabilities
• The spirit of compassion enshrined in our Constitution

I respectfully submit that a more nuanced approach—one that upholds both public safety and animal welfare—would better serve the constitutional vision of a humane society.

Alternative measures such as strengthened ABC programs, community education, and local accountability could achieve the desired outcomes without mass displacement.

I trust the Hon'ble Court will consider a path that honors both the letter and spirit of our laws.

With highest regards,
[Your Name]
[Your City]
[Date]`,
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
                            <CardTitle className="text-[#333333] text-2xl">{template.title}</CardTitle>
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