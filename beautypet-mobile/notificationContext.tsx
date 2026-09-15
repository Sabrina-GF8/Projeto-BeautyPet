import { createContext, useContext, useState } from "react";

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
};

const NotificationContext =
  createContext<NotificationContextType | undefined>(undefined);


export function NotificationProvider({
  children,
}: {
  children: React.ReactNode;
}) {

  const [notifications, setNotifications] = useState<Notification[]>([
    {
      id: 1,
      title: "Banho e tosa",
      text: "O agendamento para banho e tosa foi confirmado para o dia 02/07 às 10h.",
      read: false,
    },

    {
      id: 2,
      title: "Creche",
      text: "O agendamento para hotel ou creche foi confirmado para o dia 19/06 às 9h.",
      read: false,
    },

    {
      id: 3,
      title: "Promoção do dia",
      text: "Somente no dia 20/06/26 os banhos estarão a partir de R$ 30,00!",
      read: true,
    },
  ]);


  function readNotification(id: number) {

    setNotifications((notifications) =>
      notifications.map((notification) =>
        notification.id === id
          ? { ...notification, read: true }
          : notification
      )
    );

  }


  function clearNotifications() {
    setNotifications([]);
  }


  return (
    <NotificationContext.Provider
      value={{
        notifications,
        readNotification,
        clearNotifications,
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