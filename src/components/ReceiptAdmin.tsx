import { useState, useEffect } from "react";
import { Camera, Download, Loader2, Image as ImageIcon, X } from "lucide-react";
import { Button } from "./ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "./ui/card";
import { projectId, publicAnonKey } from "../utils/supabase/info";

interface Receipt {
  fullName: string;
  email: string;
  city: string;
  fileName: string;
  fileType: string;
  storagePath: string;
  receiptImageUrl: string | null;
  submittedAt: string;
  error?: string;
}

export function ReceiptAdmin() {
  const [receipts, setReceipts] = useState<Receipt[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedImage, setSelectedImage] = useState<string | null>(null);

  useEffect(() => {
    fetchReceipts();
  }, []);

  const fetchReceipts = async () => {
    try {
      setIsLoading(true);
      const response = await fetch(
        `https://${projectId}.supabase.co/functions/v1/make-server-7a8516f4/receipts`,
        {
          headers: {
            Authorization: `Bearer ${publicAnonKey}`,
          },
        }
      );

      if (!response.ok) {
        throw new Error("Failed to fetch receipts");
      }

      const data = await response.json();
      setReceipts(data.receipts || []);
    } catch (err) {
      console.error("Error fetching receipts:", err);
      setError(err instanceof Error ? err.message : "Failed to load receipts");
    } finally {
      setIsLoading(false);
    }
  };

  const exportToCSV = () => {
    const headers = ["Full Name", "Email", "City", "File Name", "Submitted At"];
    const rows = receipts.map((receipt) => [
      receipt.fullName,
      receipt.email,
      receipt.city,
      receipt.fileName,
      new Date(receipt.submittedAt).toLocaleString(),
    ]);

    const csvContent = [
      headers.join(","),
      ...rows.map((row) => row.map((cell) => `"${cell}"`).join(",")),
    ].join("\n");

    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `receipts-${new Date().toISOString().split("T")[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
  };

  const downloadImage = async (receipt: Receipt) => {
    if (!receipt.receiptImageUrl) {
      alert("Image URL not available. Please refresh the page.");
      return;
    }

    try {
      const response = await fetch(receipt.receiptImageUrl);
      const blob = await response.blob();
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = receipt.fileName || `receipt-${receipt.email}.jpg`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error("Error downloading image:", error);
      alert("Failed to download image. Please try again.");
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 py-8">
      <div className="container mx-auto px-4">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3">
              <Camera className="w-10 h-10 text-[#F59E0B]" />
              <div>
                <h1 className="text-3xl md:text-4xl text-[#333333]">Receipt Admin Dashboard</h1>
                <p className="text-[#666666]">View and manage uploaded post office receipts</p>
              </div>
            </div>
            <Button
              onClick={() => window.location.href = "/"}
              variant="outline"
              className="border-[#333333] text-[#333333] hover:bg-[#333333] hover:text-white"
            >
              Back to Site
            </Button>
          </div>

          {/* Stats */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <Card className="border-2 border-[#F59E0B]/20">
              <CardContent className="pt-6">
                <div className="text-center">
                  <p className="text-4xl text-[#F59E0B] mb-2">{receipts.length}</p>
                  <p className="text-[#666666]">Total Receipts</p>
                </div>
              </CardContent>
            </Card>
            <Card className="border-2 border-[#F59E0B]/20">
              <CardContent className="pt-6">
                <div className="text-center">
                  <p className="text-4xl text-[#F59E0B] mb-2">
                    {new Set(receipts.map(r => r.city)).size}
                  </p>
                  <p className="text-[#666666]">Cities Represented</p>
                </div>
              </CardContent>
            </Card>
            <Card className="border-2 border-[#F59E0B]/20">
              <CardContent className="pt-6">
                <div className="text-center">
                  <p className="text-4xl text-[#F59E0B] mb-2">
                    {receipts.length > 0 
                      ? new Date(receipts[0].submittedAt).toLocaleDateString()
                      : "N/A"}
                  </p>
                  <p className="text-[#666666]">Latest Upload</p>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Actions */}
          <div className="space-y-2">
            <div className="flex gap-4">
              <Button
                onClick={exportToCSV}
                disabled={receipts.length === 0}
                className="bg-[#F59E0B] hover:bg-[#D97706] text-white"
              >
                <Download className="w-4 h-4 mr-2" />
                Export to CSV
              </Button>
              <Button
                onClick={fetchReceipts}
                variant="outline"
                className="border-[#333333] text-[#333333] hover:bg-[#333333] hover:text-white"
              >
                Refresh Data
              </Button>
            </div>
            <p className="text-xs text-[#666666]">
              💡 Image preview links expire after 1 hour. Click "Refresh Data" to reload if images are not visible.
            </p>
          </div>
        </div>

        {/* Content */}
        {isLoading ? (
          <div className="flex items-center justify-center py-20">
            <Loader2 className="w-8 h-8 animate-spin text-[#F59E0B]" />
          </div>
        ) : error ? (
          <Card className="border-2 border-red-200 bg-red-50">
            <CardContent className="pt-6">
              <p className="text-red-800 text-center">{error}</p>
            </CardContent>
          </Card>
        ) : receipts.length === 0 ? (
          <Card className="border-2 border-gray-200">
            <CardContent className="pt-6">
              <p className="text-[#666666] text-center py-8">No receipts uploaded yet.</p>
            </CardContent>
          </Card>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {receipts.map((receipt, index) => (
              <Card key={index} className="border-2 border-gray-200 hover:border-[#F59E0B] transition-all hover:shadow-lg">
                <CardHeader className="pb-3">
                  <CardTitle className="text-lg text-[#333333]">{receipt.fullName}</CardTitle>
                  <CardDescription className="text-sm">
                    {receipt.city} • {new Date(receipt.submittedAt).toLocaleDateString()}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-3">
                  {/* Receipt Image Thumbnail */}
                  <div 
                    className="relative aspect-video bg-gray-100 rounded-lg overflow-hidden cursor-pointer group"
                    onClick={() => receipt.receiptImageUrl && setSelectedImage(receipt.receiptImageUrl)}
                  >
                    {receipt.receiptImageUrl ? (
                      <>
                        <img
                          src={receipt.receiptImageUrl}
                          alt={`Receipt from ${receipt.fullName}`}
                          className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                        />
                        <div className="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                          <ImageIcon className="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity" />
                        </div>
                      </>
                    ) : (
                      <div className="w-full h-full flex items-center justify-center text-red-500">
                        <p className="text-sm">Image unavailable</p>
                      </div>
                    )}
                  </div>

                  {/* Details */}
                  <div className="space-y-1 text-sm">
                    <p className="text-[#666666]">
                      <span className="font-medium text-[#333333]">Email:</span> {receipt.email}
                    </p>
                    <p className="text-[#666666]">
                      <span className="font-medium text-[#333333]">File:</span> {receipt.fileName}
                    </p>
                    <p className="text-[#666666] text-xs">
                      {new Date(receipt.submittedAt).toLocaleString()}
                    </p>
                  </div>

                  {/* Actions */}
                  <Button
                    onClick={() => downloadImage(receipt)}
                    variant="outline"
                    size="sm"
                    disabled={!receipt.receiptImageUrl}
                    className="w-full border-[#333333] text-[#333333] hover:bg-[#333333] hover:text-white disabled:opacity-50"
                  >
                    <Download className="w-4 h-4 mr-2" />
                    Download Image
                  </Button>
                  {receipt.error && (
                    <p className="text-xs text-red-500 text-center">{receipt.error}</p>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>

      {/* Image Modal */}
      {selectedImage && (
        <div 
          className="fixed inset-0 bg-black/80 z-50 flex items-center justify-center p-4"
          onClick={() => setSelectedImage(null)}
        >
          <button
            onClick={() => setSelectedImage(null)}
            className="absolute top-4 right-4 p-2 bg-white rounded-full hover:bg-gray-100 transition-colors"
            aria-label="Close image"
          >
            <X className="w-6 h-6 text-[#333333]" />
          </button>
          <img
            src={selectedImage}
            alt="Full size receipt"
            className="max-w-full max-h-full object-contain"
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
    </div>
  );
}
