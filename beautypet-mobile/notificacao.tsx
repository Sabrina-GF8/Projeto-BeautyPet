import {
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

import { MaterialCommunityIcons } from "@expo/vector-icons";
import { useRouter } from "expo-router";
import { ArrowLeft } from "lucide-react-native";

import { useNotifications } from "./notificationContext";

export default function Notificacao() {

  const router = useRouter();

  const {
    notifications,
    readNotification,
    clearNotifications,
  } = useNotifications();


  return (
    <SafeAreaView style={styles.container}>

      {/* ================= CABEÇALHO ================= */}

      <View style={styles.header}>

        <TouchableOpacity onPress={() => router.back()}>
          <ArrowLeft
            size={24}
            color="#222222"
          />
        </TouchableOpacity>


        <View style={styles.headerText}>

          <Text style={styles.title}>
            Notificações
          </Text>

          <Text style={styles.subtitle}>
            Consulte suas notificações
          </Text>

        </View>


        <MaterialCommunityIcons
         name="paw"
         size={22}
         color= "#FF7A45"
         />

      </View>


      {/* ================= NOTIFICAÇÕES ================= */}

      <View style={styles.notificationList}>

        {notifications.length === 0 ? (

          <View style={styles.emptyContainer}>

            <MaterialCommunityIcons
             name="paw"
             size={45}
             color= "#999999"
            />

            <Text style={styles.emptyTitle}>
              Você não possui notificações
            </Text>

            <Text style={styles.emptyText}>
              Quando houver alguma novidade, ela aparecerá aqui.
            </Text>

          </View>

        ) : (

          notifications.map((notification) => (

            <TouchableOpacity
              key={notification.id}
              style={styles.notificationCard}
              onPress={() => readNotification(notification.id)}
            >

              <View style={styles.notificationContent}>

                <Text style={styles.notificationTitle}>
                  {notification.title}
                </Text>

                <Text style={styles.notificationText}>
                  {notification.text}
                </Text>

              </View>


              <MaterialCommunityIcons
                name="paw"
                size={22}
                color={notification.read ? "#999999" : "#FF7A45"}
               />
            </TouchableOpacity>

          ))

        )}

      </View>


      {/* ================= LIMPAR ================= */}

      {notifications.length > 0 && (

        <TouchableOpacity
          style={styles.clearButton}
          onPress={clearNotifications}
        >

          <Text style={styles.clearText}>
            Limpar notificações
          </Text>

        </TouchableOpacity>

      )}

    </SafeAreaView>
  );
}


const styles = StyleSheet.create({

  container: {
    flex: 1,
    backgroundColor: "#fff4ab",
    paddingHorizontal: 12,
  },

  header: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    marginTop: 20,
  },

  headerText: {
    alignItems: "center",
  },

  title: {
    fontSize: 22,
    color: "#222222",
    fontFamily: "Poppins_500Medium",
    marginTop:35,
  },

  subtitle: {
    fontSize: 13,
    color: "#555555",
    marginTop: 3,
    fontFamily: "Poppins_400Regular",
  },

  notificationList: {
    marginTop: 32,
  },

  notificationCard: {
    height: 98,
    backgroundColor: "#FFFFFF",
    borderRadius: 10,
    marginBottom: 12,
    padding: 15,
    flexDirection: "row",
    alignItems: "flex-start",
    justifyContent: "space-between",
    elevation: 2,
  },

  notificationContent: {
    flex: 1,
    paddingRight: 10,
  },

  notificationTitle: {
    fontSize: 15,
    color: "#222222",
    fontFamily: "Poppins_500Medium",
  },

  notificationText: {
    fontSize: 12,
    color: "#444444",
    marginTop: 2,
    fontFamily: "Poppins_400Regular",
  },

  emptyContainer: {
    alignItems: "center",
    marginTop: 100,
    paddingHorizontal: 30,
  },

  emptyTitle: {
    fontSize: 15,
    color: "#555555",
    marginTop: 12,
    fontFamily: "Poppins_600SemiBold",
  },

  emptyText: {
    fontSize: 10,
    color: "#777777",
    textAlign: "center",
    marginTop: 5,
    fontFamily: "Poppins_400Regular",
  },

  clearButton: {
    alignSelf: "center",
    marginTop: 10,
  },

  clearText: {
    fontSize: 10,
    color: "#555555",
    textDecorationLine: "underline",
    fontFamily: "Poppins_400Regular",
  },

});
