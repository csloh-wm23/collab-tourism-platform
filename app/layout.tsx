import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Collab Tourism Platform",
  description: "Inclusive multilingual communication for safer, easier travel in Malaysia.",
  other: {
    "codex-preview": "development",
  },
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body className="antialiased">{children}</body>
    </html>
  );
}
