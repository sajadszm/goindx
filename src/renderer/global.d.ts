// src/renderer/global.d.ts
export {}; // Make this a module to allow global augmentation

declare global {
  interface Window {
    electronAPI?: {
      invoke: (channel: string, ...args: any[]) => Promise<any>;
      send: (channel: string, ...args: any[]) => void;
      on: (channel: string, func: (...args: any[]) => void) => (() => void) | undefined;
      // Add other methods exposed in preload.ts here as they are created
    };
  }
}
