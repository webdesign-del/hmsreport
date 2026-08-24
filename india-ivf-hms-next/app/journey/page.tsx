import JourneyView from "@/components/journey/JourneyView";

export default async function JourneyPage({
  searchParams,
}: {
  searchParams: Promise<{ id?: string }>;
}) {
  const { id } = await searchParams;

  return <JourneyView initialId={id || ""} />;
}