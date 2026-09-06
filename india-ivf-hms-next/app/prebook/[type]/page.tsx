import { notFound } from "next/navigation";
import { getPrebook } from "@/lib/api";
import { getScopedCenterId } from "@/lib/auth";
import type { PrebookType } from "@/lib/types";
import PrebookList from "@/components/prebook/PrebookList";

const VALID: PrebookType[] = ["scheduled", "missed", "cnb"];

export default async function PrebookTypePage({ params }: { params: Promise<{ type: string }> }) {
  const { type } = await params;
  if (!VALID.includes(type as PrebookType)) notFound();

  const centerId = await getScopedCenterId();
  const prebook = await getPrebook(centerId);
  return <PrebookList type={type as PrebookType} prebook={prebook} />;
}
