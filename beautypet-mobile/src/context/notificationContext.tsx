import {
  createContext,
  useContext,
  useEffect,
  useState,
} from "react";

import { api, obterTokenSessao } from "../lib/api";

type Notification = {
  id: number;
  title: string;
  text: string;
  read: boolean;
};

type NotificationContextType = {
  notifications: Notification[];
  readNotification: (id: number) => void;
  clearNotifications: () => void;
  refreshNotifications: () => Promise<void>;
};

const NotificationContext =
  createContext<NotificationContextType | undefined>(undefined);

export function NotificationProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  const [notifications, setNotifications] = useState<Notification[]>([]);

  async function refreshNotifications() {
    if (!obterTokenSessao()) {
      setNotifications([]);
      return;
    }

    try {
      const resposta = await api<{
        notificacoes: {
          id: number;
          titulo: string;
          mensagem: string;
          lida: boolean;
        }[];
      }>("notificacoes");

      setNotifications(
        (resposta.notificacoes || []).map((item) => ({
          id: Number(item.id),
          title: item.titulo,
          text: item.mensagem,
          read: Boolean(item.lida),
        }))
      );
    } catch {
      // A tela continua utilizável mesmo se a API estiver indisponível.
    }
  }

  function readNotification(id: number) {
    setNotifications((notifications) =>
      notifications.map((notification) =>
        notification.id === id
          ? { ...notification, read: true }
          : notification
      )
    );

    api("marcar_notificacao_lida", {
      notificacao_id: id,
    }).catch(() => {});
  }

  function clearNotifications() {
    setNotifications([]);
    api("limpar_notificacoes").catch(() => {});
  }

  useEffect(() => {
    refreshNotifications();
  }, []);

  return (
    <NotificationContext.Provider
      value={{
        notifications,
        readNotification,
        clearNotifications,
        refreshNotifications,
      }}
    >
      {children}
    </NotificationContext.Provider>
  );
}

export function useNotifications() {
  const context = useContext(NotificationContext);

  if (!context) {
    throw new Error(
      "useNotifications deve ser usado dentro de NotificationProvider"
    );
  }

  return context;
}
