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

import { useEffect, useState } from "react";

export default function Home() {

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

          <TouchableOpacity style={styles.notification}>

            <Bell
              size={20}
              color="#FFFFFF"
            />

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


      {/* ================= PRÓXIMO AGENDAMENTO ================= */}

      <View style={styles.appointmentCard}>

        <View style={styles.calendarIcon}>

          <CalendarDays
            size={18}
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


      {/* ================= BOTÃO AGENDAR ================= */}

      <TouchableOpacity style={styles.scheduleButton}>

        <Scissors
          size={17}
          color="#333333"
        />

        <Text style={styles.scheduleText}>
          Agendar serviço
        </Text>
        
      </TouchableOpacity>

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
    alignItems: "center",
  },
  notification: {
    width: 26,
    height: 26,
    borderRadius: 20,
    backgroundColor: "#EF3038",
    justifyContent: "center",
    alignItems: "center",
    marginTop: 25,
    marginBottom: 8,
  },
  logo:{
    width: 65,
    height: 65,
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
    marginHorizontal: 42,
    marginTop: 25,
    backgroundColor: "#FFFFFF",
    borderRadius: 10,
    flexDirection: "row",
    alignItems: "center",
    paddingHorizontal: 8,
    elevation: 2,
  },
  calendarIcon: {
    width: 50,
    height: 50,
    borderRadius: 20,
    backgroundColor: "#fff8d9",
    justifyContent: "center",
    alignItems: "center",
  },
  appointmentInfo: {
    marginLeft: 8,
  },
  appointmentLabel: {
    fontSize: 10,
    color: "#999999",
    fontFamily: "Poppins_400Regular",
  },
  appointmentService: {
    fontSize: 12,
    color: "#222222",
    fontFamily: "Poppins_700Bold",
  },
  appointmentDate: {
    fontSize: 8,
    color: "#777777",
    fontFamily: "Poppins_400Regular",
  },

  scheduleButton: {
    alignSelf: "center",
    height: 48,
    paddingHorizontal: 15,
    marginTop: 24,
    backgroundColor: "#FFFFFF",
    borderRadius: 8,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
    elevation: 2,
  },
  scheduleText: {
    marginLeft: 5,
    fontSize: 13,
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

});