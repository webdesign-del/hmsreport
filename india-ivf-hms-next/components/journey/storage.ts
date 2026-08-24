"use client";

import { useCallback, useEffect, useState } from "react";

function readStore<T>(key: string): Record<string, Record<number, T>> {
  if (typeof window === "undefined") return {};
  try {
    return JSON.parse(window.localStorage.getItem(key) || "{}");
  } catch {
    return {};
  }
}

/** localStorage-backed store keyed by patient id -> stage index -> value (mirrors the original page's jPlan/jComm/jComp stores) */
export function useJourneyStore<T>(storageKey: string) {
  const [store, setStore] = useState<Record<string, Record<number, T>>>({});

  // Sync from localStorage after mount — SSR has no window, so this can't be lazy initial state,
  // and reading it during the first client render would mismatch the server-rendered HTML.
  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect -- one-time hydration from an external store (localStorage)
    setStore(readStore<T>(storageKey));
  }, [storageKey]);

  const set = useCallback(
    (pid: string, stage: number, value: T | null) => {
      setStore((prev) => {
        const next = { ...prev, [pid]: { ...(prev[pid] || {}) } };
        if (value == null) delete next[pid][stage];
        else next[pid][stage] = value;
        try {
          window.localStorage.setItem(storageKey, JSON.stringify(next));
        } catch {
          /* ignore quota errors */
        }
        return next;
      });
    },
    [storageKey]
  );

  const get = useCallback((pid: string, stage: number): T | null => store[pid]?.[stage] ?? null, [store]);

  return { get, set };
}
