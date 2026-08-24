import { notFound } from "next/navigation";
import { getPrebook } from "@/lib/api";
import type { PrebookType } from "@/lib/types";
import PrebookList from "@/components/prebook/PrebookList";

const VALID: PrebookType[] = ["scheduled", "missed", "cnb"];

export default async function PrebookTypePage({ params }: { params: Promise<{ type: string }> }) {
  const { type } = await params;
  if (!VALID.includes(type as PrebookType)) notFound();

  const prebook = await getPrebook();
  return <PrebookList type={type as PrebookType} prebook={prebook} />;
}
