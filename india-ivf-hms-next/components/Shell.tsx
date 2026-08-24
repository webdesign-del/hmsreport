import Sidebar from "./Sidebar";
import Topbar from "./Topbar";

export default function Shell({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col">
      <Topbar />
      <div className="flex min-h-[calc(100vh-62px)] flex-1">
        <Sidebar />
        <main className="min-w-0 flex-1 overflow-x-hidden px-[30px] pb-[60px] pt-[26px]">{children}</main>
      </div>
    </div>
  );
}
