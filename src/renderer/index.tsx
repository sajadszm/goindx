// src/renderer/index.tsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from '@/renderer/App'; // Using alias

const container = document.getElementById('root');
if (container) {
  const root = createRoot(container);
  root.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  );
} else {
  console.error('Root container (div with id=root) not found in public/index.html');
}
