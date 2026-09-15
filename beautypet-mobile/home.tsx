import {
  Image,
  SafeAreaView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View
} from "react-native";

import {
  Bell, CalendarDays, Home as HomeIcon,
  Scissors,
  UserRound,
  Wallet,
} from "lucide-react-native";

import { useRouter } from "expo-router";
import { useEffect, useState } from "react";
import { useNotifications } from "./notificationContext";

export default function Home() {

   const router = useRouter();

  const { notifications } = useNotifications();

  const possuiNotificacao = notifications.some(
    (notification) => !notification.read
  );

  // ================= CARROSSEL =================

  const imagens = [
    require("../assets/dog1.jpg"),
    require("../assets/dog2.jpg"),
    require("../assets/dog3.jpg"),
  ];

  const [imagemAtual, setImagemAtual] = useState(0);

  useEffect(() => {

    const intervalo = setInterval(() => {

      setImagemAtual((imagemAtual) =>
        (imagemAtual + 1) % imagens.length
      );

    }, 3500);

    return () => clearInterval(intervalo);

  }, []);


  return (
    
    <SafeAreaView style={styles.container}>
      {/* ================= HEADER ================= */}

      <View style={styles.header}>

        <View>

          <Text style={styles.greeting}>
            Olá Marina !
          </Text>

          <Text style={styles.title}>
            Bem-vindo ao
          </Text>

          <Text style={styles.titlePet}>
            Beauty Pet
          </Text>

        </View>


        <View style={styles.headerRight}>

          {/* Botão de notificação */}

          <TouchableOpacity
  style={styles.notification}
  onPress={() => router.push("/notificacao")}
>

  <Bell 
    size={20} 
    color="#FFFFFF" 
  />

  {possuiNotificacao && (
    <View style={styles.notificationBadge} />
  )}

</TouchableOpacity>
          {/* Logo */}

          <Image
            source={require("../assets/img1.png")}
            style={styles.logo}
          />

        </View>

      </View>


      {/* ================= PROMOÇÃO ================= */}

      <View style={styles.promotionSection}>

        <Text style={styles.sectionTitle}>
          Promoções especiais
        </Text>


        {/* SOMBRA DO CARROSSEL */}

        <View style={styles.promotionShadow}>

          <TouchableOpacity style={styles.promotionCard}>

            {/* IMAGEM DO CARROSSEL */}

            <Image
              source={imagens[imagemAtual]}
              style={styles.promotionImage}
            />


            <View style={styles.promotionText}>

              <Text style={styles.promotionTitle}>
                Promoção relâmpago
              </Text>

              <Text style={styles.promotionDiscount}>
                30% OFF em vacinas
              </Text>

            </View>

          </TouchableOpacity>

        </View>

      </View>


      {/* ================= PRÓXIMO AGENDAMENTO ================= */}

      <View style={styles.cardServicos}>

  <Text style={styles.tituloServico}>
    Serviço
  </Text>

  <View style={styles.appointmentCard}>

    <View style={styles.calendarIcon}>

      <CalendarDays
        size={20}
        color="#555555"
      />

    </View>

    <View style={styles.appointmentInfo}>

      <Text style={styles.appointmentLabel}>
        Próximo agendamento
      </Text>

      <Text style={styles.appointmentService}>
        Creche
      </Text>

      <Text style={styles.appointmentDate}>
        19 Jun, 2026 • 10:00
      </Text>

    </View>

  </View>

  <TouchableOpacity style={styles.scheduleButton}>

    <Scissors
      size={20}
      color="#333333"
    />

    <Text style={styles.scheduleText}>
      Agendar serviço
    </Text>

  </TouchableOpacity>

</View>

      {/* ================= MENU INFERIOR ================= */}

      <View style={styles.bottomMenu}>

        <TouchableOpacity style={styles.menuItem}>

          <HomeIcon
            size={24}
            color="#FF4D55"
          />

          <Text style={styles.menuTextActive}>
            Home
          </Text>

        </TouchableOpacity>


        <TouchableOpacity style={styles.menuItem}>

          <CalendarDays
            size={23}
            color="#777777"
          />

          <Text style={styles.menuText}>
            Agenda
          </Text>

        </TouchableOpacity>


        <TouchableOpacity style={styles.menuItem}>

          <Wallet
            size={23}
            color="#777777"
          />

          <Text style={styles.menuText}>
            Financeiro
          </Text>

        </TouchableOpacity>


        <TouchableOpacity style={styles.menuItem}>

          <UserRound
            size={23}
            color="#777777"
          />

          <Text style={styles.menuText}>
            Perfil
          </Text>

        </TouchableOpacity>

      </View>

    </SafeAreaView>
  );
}

const styles = StyleSheet.create({

  container: {
    flex: 1,
    backgroundColor: "#FFF4A8",
  },
  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    paddingHorizontal: 12,
    paddingTop: 10,
  },
  greeting: {
    fontSize: 20,
    color: "#333333",
    fontFamily: "Poppins_400Regular",
    marginTop: 70,
  },
  title: {
    fontSize: 22,
    color: "#111111",
    fontFamily: "Poppins_700Bold",
    marginTop: 8,
  },
  titlePet: {
    fontSize: 21,
    color: "#FF3038",
    fontFamily: "Poppins_700Bold",
  },
  headerRight: {
    flexDirection:"row",
    alignItems: "center",
  },
  notification: {
    width: 43,
    height: 43,
    borderRadius: 20,
    backgroundColor: "#EF3038",
    justifyContent: "center",
    alignItems: "center",
    marginTop: 25,
    marginRight: 12,
    marginBottom: 20,
  },
  notificationBadge: {
  position: "absolute",
  top: -2,
  right: -2,
  width: 8,
  height: 8,
  borderRadius: 10,
  backgroundColor: "#000000",
},
  logo: {
    width: 90,
    height: 90,
    borderRadius: 40,
    resizeMode: "contain",
  },
  promotionSection: {
    marginTop: 34,
    paddingHorizontal: 12,
  },
  sectionTitle: {
    fontSize: 20,
    color: "#555555",
    fontFamily: "Poppins_400Regular",
    marginBottom: 8,
  },
  promotionShadow: {
    width: "100%",
  height: 200,
  borderRadius: 10,
  elevation: 8,
  shadowColor: "#000",
  shadowOffset: {
    width: 0,
    height: 4,
  },
  shadowOpacity: 0.30,
  shadowRadius: 6,
  backgroundColor: "#FFFFFF",
  },
  promotionCard: {
    width: "100%",
    height: 200,
    borderRadius: 10,
    overflow: "hidden",
    backgroundColor: "#FFFFFF",
  },
  promotionImage: {
    width: "100%",
    height: "100%",
    resizeMode: "cover",
  },
  promotionText: {
    position: "absolute",
    bottom: 7,
    left: 7,
  },
  promotionTitle: {
    color: "#000",
    fontSize: 12,
    fontFamily: "Poppins_700Bold",
  },
  promotionDiscount: {
    color: "#000",
    fontSize: 9,
    fontFamily: "Poppins_700Bold",
  },
 appointmentCard: {
  height: 68,
  marginTop: 15,
  backgroundColor: "#FFF4A8",
  borderRadius: 10,
  flexDirection: "row",
  alignItems: "center",
  paddingHorizontal: 8, 

},
  calendarIcon: {
    width: 50,
    height: 50,
    borderRadius: 20,
    backgroundColor: "#ffffff",
    justifyContent: "center",
    alignItems: "center",
  },
  appointmentInfo: {
    marginLeft: 8,
  },
  appointmentLabel: {
    fontSize: 12,
    color: "#999999",
    fontFamily: "Poppins_400Regular",
  },
  appointmentService: {
    fontSize: 13,
    color: "#222222",
    fontFamily: "Poppins_700Bold",
    marginBottom: 2,
  },
  appointmentDate: {
    fontSize: 10,
    color: "#777777",
    fontFamily: "Poppins_400Regular",
  },
  scheduleButton: {
    alignSelf: "center",
    height: 48,
    paddingHorizontal: 20,
    marginTop: 24,
    backgroundColor: "#FFFFFF",
    borderRadius: 8,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    elevation: 2,
    borderWidth: 1,
    borderColor: "#9b9b9b",
  },
  scheduleText: {
    marginLeft: 5,
    fontSize: 14,
    color: "#333333",
    fontFamily: "Poppins_600SemiBold",
  },
  bottomMenu: {
    position: "absolute",
    bottom: 2,
    left: 0,
    right: 0,
    height: 70,
    backgroundColor: "#FFFFFF",
    flexDirection: "row",
    justifyContent: "space-around",
    alignItems: "center",
    borderTopWidth: 1,
    borderTopColor: "#EEEEEE",
  },
  menuItem: {
    alignItems: "center",
    justifyContent: "center",
    width: 70,
  },
  menuText: {
    fontSize: 12,
    color: "#777777",
    marginTop: 2,
    fontFamily: "Poppins_400Regular",
  },
  menuTextActive: {
    fontSize: 12,
    color: "#FF4D55",
    marginTop: 2,
    fontFamily: "Poppins_600SemiBold",
  },
  cardServicos: {
    marginHorizontal: 42,
  marginTop: 25,
  padding: 15,
  backgroundColor: "#FFFFFF",
  borderRadius: 10,
  elevation: 2, 
  },
  tituloServico: {
   fontSize: 18,
  fontFamily: "Poppins_700Bold", 
  },
});
