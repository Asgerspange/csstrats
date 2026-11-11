import { useToast as usePrimevueToast } from 'primevue/usetoast';

export type ToastSeverity = 'success' | 'info' | 'warn' | 'error' | 'secondary' | 'contrast';

export interface ToastMessage {
  summary: string;
  detail?: string;
  life?: number;
}

export function useToast() {
  const toast = usePrimevueToast();

  const show = (severity: ToastSeverity, summary: string, detail?: string, life = 3000) => {
    toast.add({
      severity,
      summary,
      detail,
      life,
    });
  };

  const success = (summary: string, detail?: string) => show('success', summary, detail);
  const error = (summary: string, detail?: string) => show('error', summary, detail);
  const warn = (summary: string, detail?: string) => show('warn', summary, detail);
  const info = (summary: string, detail?: string) => show('info', summary, detail);

  return {
    show,
    success,
    error,
    warn,
    info,
  };
}