import { getTriggers } from "@/lib/api";
import { getScopedCenterId } from "@/lib/auth";
import TriggerPileup from "@/components/triggers/TriggerPileup";

export default async function TriggersPage() {
  const centerId = await getScopedCenterId();
  const triggers = await getTriggers(centerId);
  return <TriggerPileup triggers={triggers} />;
}
