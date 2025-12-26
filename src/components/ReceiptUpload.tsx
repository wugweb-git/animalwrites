import { useState } from "react";
import { Upload, Check, AlertCircle, Camera, Loader2 } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "./ui/card";
import { Input } from "./ui/input";
import { Label } from "./ui/label";
import { motion } from "motion/react";
import { projectId, publicAnonKey } from "../utils/supabase/info";

// Helper function to compress image
const compressImage = async (file: File, maxSizeMB = 2): Promise<File> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = (event) => {
      const img = new Image();
      img.src = event.target?.result as string;
      img.onload = () => {
        const canvas = document.createElement('canvas');
        let width = img.width;
        let height = img.height;
        
        // Calculate new dimensions (max 1920px width)
        const maxWidth = 1920;
        if (width > maxWidth) {
          height = (height * maxWidth) / width;
          width = maxWidth;
        }
        
        canvas.width = width;
        canvas.height = height;
        
        const ctx = canvas.getContext('2d');
        ctx?.drawImage(img, 0, 0, width, height);
        
        // Convert to blob with compression
        canvas.toBlob(
          (blob) => {
            if (blob) {
              const compressedFile = new File([blob], file.name, {
                type: 'image/jpeg',
                lastModified: Date.now(),
              });
              resolve(compressedFile);
            } else {
              reject(new Error('Failed to compress image'));
            }
          },
          'image/jpeg',
          0.85 // Quality 85%
        );
      };
      img.onerror = reject;
    };
    reader.onerror = reject;
  });
};

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
  const [uploadProgress, setUploadProgress] = useState(0);

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const handleFileSelect = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      // Validate file type
      if (!file.type.startsWith("image/")) {
        setErrorMessage("Please upload an image file (JPG, PNG, HEIC, etc.)");
        setSubmitStatus("error");
        return;
      }

      // Validate file size (max 20MB before compression)
      if (file.size > 20 * 1024 * 1024) {
        setErrorMessage("File size must be less than 20MB");
        setSubmitStatus("error");
        return;
      }

      try {
        // Compress image if larger than 2MB
        let processedFile = file;
        if (file.size > 2 * 1024 * 1024) {
          console.log(`Compressing image from ${(file.size / 1024 / 1024).toFixed(2)}MB...`);
          processedFile = await compressImage(file);
          console.log(`Compressed to ${(processedFile.size / 1024 / 1024).toFixed(2)}MB`);
        }

        setSelectedFile(processedFile);
        setSubmitStatus("idle");
        setErrorMessage("");

        // Create preview
        const reader = new FileReader();
        reader.onloadend = () => {
          setPreviewUrl(reader.result as string);
        };
        reader.readAsDataURL(processedFile);
      } catch (error) {
        console.error("Error processing image:", error);
        setErrorMessage("Failed to process image. Please try another photo.");
        setSubmitStatus("error");
      }
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

      // Simulate upload progress
      setUploadProgress(10);

      // Convert image to base64
      setUploadProgress(30);
      const base64Image = await new Promise<string>((resolve, reject) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result as string);
        reader.onerror = reject;
        reader.readAsDataURL(selectedFile);
      });

      setUploadProgress(50);

      // Submit to backend
      setUploadProgress(70);
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
        console.error("Receipt upload failed:", errorData);
        throw new Error(errorData.details || errorData.error || "Failed to submit receipt");
      }

      const result = await response.json();
      console.log("Receipt uploaded successfully:", result);

      setUploadProgress(100);

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
    <section id="upload-receipt" className="py-16 md:py-24 bg-background">
      <div className="container mx-auto px-4">
        <div className="max-w-3xl mx-auto">
          <div className="text-center mb-12">
            <div className="inline-flex items-center gap-3 mb-4">
              <Camera className="w-7 h-7 md:w-8 md:h-8 text-accent" />
              <h2 id="upload-receipt-heading" className="text-3xl md:text-5xl text-foreground">Share Your Impact</h2>
            </div>
            <p className="text-base md:text-xl text-muted-foreground max-w-2xl mx-auto leading-relaxed">
              Posted your letter? Amazing! Upload your post office receipt to inspire others and help us track our collective impact.
            </p>
          </div>

          <Card className="border-2 border-accent/20 shadow-[var(--elevation-xl)] bg-card">
            <CardHeader className="bg-gradient-to-r from-[var(--surface-warm)] to-background">
              <CardTitle className="text-foreground text-xl md:text-2xl">Upload Your Receipt</CardTitle>
              <CardDescription className="text-muted-foreground text-sm md:text-base">
                Help us celebrate every voice that stands for compassion
              </CardDescription>
            </CardHeader>
            <CardContent className="pt-6">
              <form onSubmit={handleSubmit} className="space-y-6">
                {/* Full Name */}
                <div className="space-y-2">
                  <Label htmlFor="fullName" className="text-foreground text-base">
                    Full Name <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="fullName"
                    name="fullName"
                    type="text"
                    value={formData.fullName}
                    onChange={handleInputChange}
                    placeholder="Enter your full name"
                    required
                    className="border-border focus:border-accent focus:ring-accent h-14 text-lg bg-input-background"
                    disabled={isSubmitting}
                  />
                </div>

                {/* Email */}
                <div className="space-y-2">
                  <Label htmlFor="email" className="text-foreground text-base">
                    Email <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="email"
                    name="email"
                    type="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    placeholder="your.email@example.com"
                    required
                    className="border-border focus:border-accent focus:ring-accent h-14 text-lg bg-input-background"
                    disabled={isSubmitting}
                  />
                </div>

                {/* City */}
                <div className="space-y-2">
                  <Label htmlFor="city" className="text-foreground text-base">
                    City <span className="text-destructive">*</span>
                  </Label>
                  <Input
                    id="city"
                    name="city"
                    type="text"
                    value={formData.city}
                    onChange={handleInputChange}
                    placeholder="Your city name"
                    required
                    className="border-border focus:border-accent focus:ring-accent h-14 text-lg bg-input-background"
                    disabled={isSubmitting}
                  />
                </div>

                {/* File Upload */}
                <div className="space-y-2">
                  <Label htmlFor="receipt" className="text-foreground text-base">
                    Receipt Photo <span className="text-destructive">*</span>
                  </Label>
                  <div className="border-2 border-dashed border-border rounded-lg p-6 md:p-6 text-center hover:border-accent transition-colors min-h-[150px] flex items-center justify-center bg-input-background">
                    <input
                      id="receipt"
                      type="file"
                      accept="image/*,image/heic,image/heif"
                      onChange={handleFileSelect}
                      className="hidden"
                      disabled={isSubmitting}
                    />
                    <label
                      htmlFor="receipt"
                      className="cursor-pointer flex flex-col items-center gap-4 w-full"
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
                            className="max-h-48 md:max-h-64 max-w-full mx-auto rounded-lg border border-border object-contain"
                          />
                          <p className="text-sm text-muted-foreground break-all px-2">
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
                            className="border-foreground text-foreground hover:bg-foreground hover:text-background min-h-[44px] px-6 text-base touch-manipulation"
                          >
                            Change Photo
                          </Button>
                        </motion.div>
                      ) : (
                        <>
                          <Camera className="w-14 h-14 text-accent" />
                          <div>
                            <p className="text-foreground font-medium text-lg">
                              📸 Choose Photo or Take New
                            </p>
                            <p className="text-base text-muted-foreground mt-2">
                              JPG, PNG or HEIC • Up to 20MB
                            </p>
                            <p className="text-sm text-muted-foreground/70 mt-1">
                              Large images auto-compressed
                            </p>
                          </div>
                        </>
                      )}
                    </label>
                  </div>
                </div>

                {/* Consent Checkbox */}
                <div className="p-5 md:p-4 bg-[var(--surface-warm)] rounded-lg border border-accent/20">
                  <label htmlFor="receipt-consent" className="flex items-start gap-4 cursor-pointer group">
                    <input
                      type="checkbox"
                      id="receipt-consent"
                      checked={consent}
                      onChange={(e) => setConsent(e.target.checked)}
                      className="mt-1 h-6 w-6 text-accent focus:ring-accent focus:ring-offset-2 border-border rounded cursor-pointer flex-shrink-0"
                      required
                      aria-required="true"
                      disabled={isSubmitting}
                    />
                    <span className="text-muted-foreground leading-relaxed group-hover:text-foreground transition-colors text-[12px]">
                      I consent to sharing my receipt and information with the Animal Writes campaign. I understand this data will be used to track campaign impact and may be displayed publicly to inspire participation. My personal information will be kept confidential.
                    </span>
                  </label>
                </div>

                {/* Status Messages */}
                {submitStatus === "success" && (
                  <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex items-start gap-3 p-4 bg-green-50 border border-green-200 rounded-lg"
                  >
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <p className="text-green-800 text-sm md:text-base">
                      Thank you! Your receipt has been uploaded successfully. You're making a difference! 🐕
                    </p>
                  </motion.div>
                )}

                {submitStatus === "error" && errorMessage && (
                  <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="flex items-start gap-3 p-4 bg-red-50 border border-red-200 rounded-lg"
                  >
                    <AlertCircle className="w-5 h-5 text-destructive flex-shrink-0 mt-0.5" />
                    <p className="text-destructive text-sm md:text-base">{errorMessage}</p>
                  </motion.div>
                )}

                {/* Submit Button */}
                <Button
                  type="submit"
                  disabled={isSubmitting || !formData.fullName || !formData.email || !formData.city || !selectedFile || !consent}
                  className="w-full bg-accent hover:bg-accent-hover text-white py-7 text-lg disabled:opacity-50 disabled:cursor-not-allowed touch-manipulation relative overflow-hidden"
                >
                  {isSubmitting && uploadProgress > 0 && (
                    <motion.div
                      initial={{ width: "0%" }}
                      animate={{ width: `${uploadProgress}%` }}
                      transition={{ duration: 0.3 }}
                      className="absolute left-0 top-0 bottom-0 bg-accent-hover opacity-50"
                    />
                  )}
                  <span className="relative z-10">
                    {isSubmitting ? (
                      <>
                        <Loader2 className="w-5 h-5 mr-2 animate-spin inline" />
                        Uploading... {uploadProgress}%
                      </>
                    ) : (
                      <>
                        <Upload className="w-5 h-5 mr-2 inline" />
                        Upload Receipt
                      </>
                    )}
                  </span>
                </Button>

                <p className="text-xs md:text-sm text-muted-foreground text-center leading-relaxed px-2">
                  🔒 Your information and images are securely stored and will be kept private. Used only to track campaign impact.
                </p>
              </form>
            </CardContent>
          </Card>

          {/* Info Box */}
          <div className="mt-8 p-4 md:p-6 bg-[var(--surface-warm)] rounded-lg border border-accent/20">
            <h3 className="text-foreground font-semibold mb-3 text-base md:text-lg">Why share your receipt?</h3>
            <ul className="space-y-2 text-muted-foreground text-sm md:text-base">
              <li className="flex items-start gap-2">
                <Check className="w-4 h-4 text-accent flex-shrink-0 mt-0.5" />
                <span>Help us track the real-world impact of this movement</span>
              </li>
              <li className="flex items-start gap-2">
                <Check className="w-4 h-4 text-accent flex-shrink-0 mt-0.5" />
                <span>Inspire others to take action when they see participation numbers grow</span>
              </li>
              <li className="flex items-start gap-2">
                <Check className="w-4 h-4 text-accent flex-shrink-0 mt-0.5" />
                <span>Create a public record of citizen engagement for this cause</span>
              </li>
              <li className="flex items-start gap-2">
                <Check className="w-4 h-4 text-accent flex-shrink-0 mt-0.5" />
                <span>Your receipt is proof that you stood up for compassion</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </section>
  );
}