import {
  createContext,
  useContext,
  useEffect,
  useState,
} from "react";

import { Alert } from "react-native";

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
    }).catch(() => {
      // HEURÍSTICA 9: antes parecia salvo mesmo quando falhava; agora desfaz a marcação.
      setNotifications((notifications) =>
        notifications.map((notification) =>
          notification.id === id
            ? { ...notification, read: false }
            : notification
        )
      );
    });
  }

  function clearNotifications() {
    const anteriores = notifications;
    setNotifications([]);
    api("limpar_notificacoes").catch((erro) => {
      // HEURÍSTICA 9: restaura a lista e explica o que aconteceu.
      setNotifications(anteriores);
      Alert.alert(
        "Não foi possível limpar as notificações",
        erro instanceof Error ? erro.message : "Verifique sua conexão e tente novamente."
      );
    });
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
