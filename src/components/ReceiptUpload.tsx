import { useState } from "react";
import { Upload, Check, AlertCircle, Camera, Loader2 } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "./ui/card";
import { Input } from "./ui/input";
import { Label } from "./ui/label";
import { motion } from "motion/react";
import { projectId, publicAnonKey } from "../utils/supabase/info";

export function ReceiptUpload() {
  const [formData, setFormData] = useState({
    fullName: "",
    email: "",
    city: "",
  });
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [consent, setConsent] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitStatus, setSubmitStatus] = useState<"idle" | "success" | "error">("idle");
  const [errorMessage, setErrorMessage] = useState("");

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      // Validate file type
      if (!file.type.startsWith("image/")) {
        setErrorMessage("Please upload an image file (JPG, PNG, etc.)");
        setSubmitStatus("error");
        return;
      }

      // Validate file size (max 5MB)
      if (file.size > 5 * 1024 * 1024) {
        setErrorMessage("File size must be less than 5MB");
        setSubmitStatus("error");
        return;
      }

      setSelectedFile(file);
      setSubmitStatus("idle");
      setErrorMessage("");

      // Create preview
      const reader = new FileReader();
      reader.onloadend = () => {
        setPreviewUrl(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setSubmitStatus("idle");
    setErrorMessage("");

    try {
      // Validate all fields
      if (!formData.fullName || !formData.email || !formData.city || !selectedFile) {
        setErrorMessage("Please fill in all fields and upload a receipt image");
        setSubmitStatus("error");
        setIsSubmitting(false);
        return;
      }

      // Validate consent
      if (!consent) {
        setErrorMessage("Please accept the consent to proceed");
        setSubmitStatus("error");
        setIsSubmitting(false);
        return;
      }

      // Validate email format
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(formData.email)) {
        setErrorMessage("Please enter a valid email address");
        setSubmitStatus("error");
        setIsSubmitting(false);
        return;
      }

      // Convert image to base64
      const base64Image = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result as string);
        reader.onerror = reject;
        reader.readAsDataURL(selectedFile);
      });

      // Submit to backend
      const response = await fetch(
        `https://${projectId}.supabase.co/functions/v1/make-server-7a8516f4/receipts`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${publicAnonKey}`,
          },
          body: JSON.stringify({
            fullName: formData.fullName,
            email: formData.email,
            city: formData.city,
            receiptImage: base64Image,
            fileName: selectedFile.name,
            fileType: selectedFile.type,
            timestamp: new Date().toISOString(),
          }),
        }
      );

      if (!response.ok) {
        const errorData = await response.json();
        throw new Error(errorData.error || "Failed to submit receipt");
      }

      const result = await response.json();
      console.log("Receipt uploaded successfully:", result);

      // Success!
      setSubmitStatus("success");
      
      // Reset form after 3 seconds
      setTimeout(() => {
        setFormData({ fullName: "", email: "", city: "" });
        setSelectedFile(null);
        setPreviewUrl(null);
        setConsent(false);
        setSubmitStatus("idle");
      }, 3000);
    } catch (error) {
      console.error("Error uploading receipt:", error);
      setErrorMessage(error instanceof Error ? error.message : "Failed to upload receipt. Please try again.");
      setSubmitStatus("error");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <section id="upload-receipt" className="py-16 md:py-24 bg-white">
      <div className="container mx-auto px-4">
        <div className="max-w-3xl mx-auto">
          <div className="text-center mb-12">
            <div className="inline-flex items-center gap-3 mb-4">
              <Camera className="w-8 h-8 text-[#F59E0B]" />
              <h2 className="text-4xl md:text-5xl text-[#333333]">Share Your Impact</h2>
            </div>
            <p className="text-xl text-[#666666] max-w-2xl mx-auto">
              Posted your letter? Amazing! Upload your post office receipt to inspire others and help us track our collective impact.
            </p>
          </div>

          <Card className="border-2 border-[#F59E0B]/20 shadow-xl">
            <CardHeader className="bg-gradient-to-r from-[#FFF8F3] to-white">
              <CardTitle className="text-[#333333] text-2xl">Upload Your Receipt</CardTitle>
              <CardDescription className="text-[#666666]">
                Help us celebrate every voice that stands for compassion
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-6">
              <form onSubmit={handleSubmit} className="space-y-6">
                {/* Full Name */}
                <div className="space-y-2">
                  <Label htmlFor="fullName" className="text-[#333333]">
                    Full Name <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="fullName"
                    name="fullName"
                    type="text"
                    value={formData.fullName}
                    onChange={handleInputChange}
                    placeholder="Enter your full name"
                    required
                    className="border-gray-300 focus:border-[#F59E0B] focus:ring-[#F59E0B]"
                    disabled={isSubmitting}
                  />
                </div>

                {/* Email */}
                <div className="space-y-2">
                  <Label htmlFor="email" className="text-[#333333]">
                    Email <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="email"
                    name="email"
                    type="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    placeholder="your.email@example.com"
                    required
                    className="border-gray-300 focus:border-[#F59E0B] focus:ring-[#F59E0B]"
                    disabled={isSubmitting}
                  />
                </div>

                {/* City */}
                <div className="space-y-2">
                  <Label htmlFor="city" className="text-[#333333]">
                    City <span className="text-red-500">*</span>
                  </Label>
                  <Input
                    id="city"
                    name="city"
                    type="text"
                    value={formData.city}
                    onChange={handleInputChange}
                    placeholder="Your city name"
                    required
                    className="border-gray-300 focus:border-[#F59E0B] focus:ring-[#F59E0B]"
                    disabled={isSubmitting}
                  />
                </div>

                {/* File Upload */}
                <div className="space-y-2">
                  <Label htmlFor="receipt" className="text-[#333333]">
                    Receipt Photo <span className="text-red-500">*</span>
                  </Label>
                  <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center hover:border-[#F59E0B] transition-colors">
                    <input
                      id="receipt"
                      type="file"
                      accept="image/*"
                      capture="environment"
                      onChange={handleFileSelect}
                      className="hidden"
                      disabled={isSubmitting}
                    />
                    <label
                      htmlFor="receipt"
                      className="cursor-pointer flex flex-col items-center gap-4"
                    >
                      {previewUrl ? (
                        <motion.div
                          initial={{ opacity: 0, scale: 0.9 }}
                          animate={{ opacity: 1, scale: 1 }}
                          className="space-y-4 w-full"
                        >
                          <img
                            src={previewUrl}
                            alt="Receipt preview"
                            className="max-h-64 mx-auto rounded-lg border border-gray-200"
                          />
                          <p className="text-sm text-[#666666]">
                            {selectedFile?.name} ({(selectedFile!.size / 1024).toFixed(0)} KB)
                          </p>
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={(e) => {
                              e.preventDefault();
                              setSelectedFile(null);
                              setPreviewUrl(null);
                            }}
                            className="border-[#333333] text-[#333333] hover:bg-[#333333] hover:text-white"
                          >
                            Change Photo
                          </Button>
                        </motion.div>
                      ) : (
                        <>
                          <Upload className="w-12 h-12 text-[#F59E0B]" />
                          <div>
                            <p className="text-[#333333] font-medium">
                              Click to upload or take a photo
                            </p>
                            <p className="text-sm text-[#666666] mt-1">
                              JPG, PNG or HEIC (Max 5MB)
                            </p>
                          </div>
                        </>
                      )}
                    </label>
                  </div>
                </div>

                {/* Consent Checkbox */}
                <div className="flex items-start gap-3 p-4 bg-[#FFF8F3] rounded-lg border border-[#F59E0B]/20">
                  <input
                    type="checkbox"
                    id="receipt-consent"
                    checked={consent}
                    onChange={(e) => setConsent(e.target.checked)}
                    className="mt-1 h-4 w-4 text-[#F59E0B] focus:ring-[#F59E0B] border-gray-300 rounded cursor-pointer"
                    required
                    aria-required="true"
                    disabled={isSubmitting}
                  />
                  <Label htmlFor="receipt-consent" className="text-xs md:text-sm text-[#666666] cursor-pointer">
                    I consent to sharing my receipt and information with the Animal Writes campaign. I understand this data will be used to track campaign impact and may be displayed publicly to inspire participation. My personal information will be kept confidential.
                  </Label>
                </div>

                {/* Status Messages */}
                {submitStatus === "success" && (
                  <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg"
                  >
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0" />
                    <p className="text-green-800">
                      Thank you! Your receipt has been uploaded successfully. You're making a difference! 🐕
                    </p>
                  </motion.div>
                )}

                {submitStatus === "error" && errorMessage && (
                  <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-lg"
                  >
                    <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0" />
                    <p className="text-red-800">{errorMessage}</p>
                  </motion.div>
                )}

                {/* Submit Button */}
                <Button
                  type="submit"
                  disabled={isSubmitting || !formData.fullName || !formData.email || !formData.city || !selectedFile || !consent}
                  className="w-full bg-[#F59E0B] hover:bg-[#D97706] text-white py-6 text-lg disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  {isSubmitting ? (
                    <>
                      <Loader2 className="w-5 h-5 mr-2 animate-spin" />
                      Uploading...
                    </>
                  ) : (
                    <>
                      <Upload className="w-5 h-5 mr-2" />
                      Upload Receipt
                    </>
                  )}
                </Button>

                <p className="text-sm text-[#666666] text-center">
                  🔒 Your information and images are securely stored and will be kept private. Used only to track campaign impact.
                </p>
              </form>
            </CardContent>
          </Card>

          {/* Info Box */}
          <div className="mt-8 p-6 bg-[#FFF8F3] rounded-lg border border-[#F59E0B]/20">
            <h3 className="text-[#333333] font-semibold mb-3">Why share your receipt?</h3>
            <ul className="space-y-2 text-[#666666] text-sm">
              <li className="flex items-start gap-2">
                <Check className="w-4 h-4 text-[#F59E0B] flex-shrink-0 mt-0.5" />
                <span>Help us track the real-world impact of this movement</span>
              </li>
              <li className="flex items-start gap-2">
                <Check className="w-4 h-4 text-[#F59E0B] flex-shrink-0 mt-0.5" />
                <span>Inspire others to take action when they see participation numbers grow</span>
              </li>
              <li className="flex items-start gap-2">
                <Check className="w-4 h-4 text-[#F59E0B] flex-shrink-0 mt-0.5" />
                <span>Create a public record of citizen engagement for this cause</span>
              </li>
              <li className="flex items-start gap-2">
                <Check className="w-4 h-4 text-[#F59E0B] flex-shrink-0 mt-0.5" />
                <span>Your receipt is proof that you stood up for compassion</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>
  );
}
