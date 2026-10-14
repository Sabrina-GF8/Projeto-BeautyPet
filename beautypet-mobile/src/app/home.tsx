import {
  Image,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

import {
  Bell,
  CalendarDays,
  ChevronRight,
  Home as HomeIcon,
  UserRound,
  Wallet
} from "lucide-react-native";

import { useRouter } from "expo-router";
import { useEffect, useState } from "react";
import { useNotifications } from "../context/notificationContext";
import { api, obterClienteSessao } from "../lib/api";

type ProximoAgendamento = {
  id: number;
  data_hora_inicio: string;
  status: string;
  pet: string;
  servicos: string | null;
};

export default function Home() {
  const router = useRouter();

  const { notifications, refreshNotifications } = useNotifications();

  const possuiNotificacao = notifications.some(
    (notification) => !notification.read
  );

  const [nomeTutor, setNomeTutor] = useState(
    obterClienteSessao()?.nome || ""
  );
  const [proximoAgendamento, setProximoAgendamento] =
    useState<ProximoAgendamento | null>(null);

  useEffect(() => {
    refreshNotifications();

    api<{
      cliente: { nome: string };
      proximo_agendamento: ProximoAgendamento | null;
    }>("home")
      .then((resposta) => {
        setNomeTutor(resposta.cliente?.nome || "");
        setProximoAgendamento(resposta.proximo_agendamento || null);
      })
      .catch(() => {
        // Mantém a tela funcionando mesmo se a API estiver temporariamente indisponível.
      });
  }, []);

  function formatarDataAgendamento(valor?: string | null) {
    if (!valor) {
      return "Nenhum horário confirmado";
    }

    const data = new Date(valor.replace(" ", "T"));

    if (Number.isNaN(data.getTime())) {
      return valor;
    }

    return data.toLocaleString("pt-BR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
      hour: "2-digit",
      minute: "2-digit",
    });
  }

  // ================= CARROSSEL =================

  const imagens = [
    require("../assets/promo_gatos.png"),
    require("../assets/promo_spa.png"),
    require("../assets/promo_caes.png"),
  ];

  const [imagemAtual, setImagemAtual] = useState(0);

  useEffect(() => {
    const intervalo = setInterval(() => {
      setImagemAtual((imagemAtual) => (imagemAtual + 1) % imagens.length);
    }, 3500);

    return () => clearInterval(intervalo);
  }, []);

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        showsVerticalScrollIndicator={false}
        contentContainerStyle={styles.scrollContent}
      >
        {/* ================= HEADER ================= */}

        <View style={styles.header}>
          <View style={styles.headerText}>
            <Text style={styles.greeting}>Olá {nomeTutor || "Tutor"}!</Text>

            <Text style={styles.title}>Bem-vindo ao</Text>

            <Text style={styles.titlePet}>Beauty Pet</Text>

            <Text style={styles.subtitle}>
              Cuidado e bem-estar para quem você ama.
            </Text>
          </View>

          <View style={styles.headerRight}>
            <TouchableOpacity
              style={styles.notification}
              onPress={() => router.push("/notificacao")}
              activeOpacity={0.8}
            >
              <Bell size={21} color="#FFFFFF" />

              {possuiNotificacao && (
                <View style={styles.notificationBadge} />
              )}
            </TouchableOpacity>

            <Image
              source={require("../assets/img1.png")}
              style={styles.logo}
            />
          </View>
        </View>

        {/* ================= PROMOÇÃO ================= */}

        <View style={styles.promotionSection}>
          <View style={styles.sectionHeader}>
            <Text style={styles.sectionTitle}>
              Promoções especiais
            </Text>

          </View>

          <View style={styles.promotionShadow}>
            <TouchableOpacity
              style={styles.promotionCard}
              activeOpacity={0.9}
            >
              <Image
                source={imagens[imagemAtual]}
                style={styles.promotionImage}
              />

              
            </TouchableOpacity>
          </View>

          {/* INDICADORES DO CARROSSEL */}

          <View style={styles.dots}>
            {imagens.map((_, index) => (
              <View
                key={index}
                style={[
                  styles.dot,
                  imagemAtual === index && styles.dotActive,
                ]}
              />
            ))}
          </View>
        </View>

        {/* ================= SERVIÇO ================= */}

        <View style={styles.serviceSection}>
          <Text style={styles.tituloServico}>
            Serviço
          </Text>

          <View style={styles.appointmentCard}>
            <View style={styles.calendarIcon}>
              <CalendarDays
                size={24}
                color="#272727"
              />
            </View>

            <View style={styles.appointmentInfo}>
              <Text style={styles.appointmentLabel}>
                Próximo agendamento
              </Text>

              <Text style={styles.appointmentService}>
                {proximoAgendamento?.servicos || "Nenhum agendamento"}
              </Text>

              <Text style={styles.appointmentDate}>
                {formatarDataAgendamento(proximoAgendamento?.data_hora_inicio)}
              </Text>
            </View>

          
          </View>

          <TouchableOpacity
            style={styles.scheduleButton}
            activeOpacity={0.85}
            onPress={() => router.push("/escolher_pet")}
          >
            <CalendarDays
              size={23}
              color="#FFFFFF"
            />

            <Text style={styles.scheduleText}>
              Agendar serviço
            </Text>

            <ChevronRight
              size={22}
              color="#FFFFFF"
            />
          </TouchableOpacity>
        </View>
      </ScrollView>

      {/* ================= MENU INFERIOR ================= */}

      <View style={styles.bottomMenu}>
        <TouchableOpacity style={styles.menuItem}>
          <HomeIcon
            size={25}
            color="#EF3038"
          />

          <Text style={styles.menuTextActive}>
            Home
          </Text>

          <View style={styles.activeLine} />
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem}
        onPress={() => router.push("/agenda")}>
          <CalendarDays
            size={24}
            color="#5F6269"
          />

          <Text style={styles.menuText}>
            
            Agenda
          </Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem}
        onPress={() => router.push("/financeiro")}>
          <Wallet
            size={24}
            color="#5F6269"
          />

          <Text style={styles.menuText}>
            Financeiro
          </Text>
        </TouchableOpacity>

        <TouchableOpacity style={styles.menuItem}
         onPress={() => router.push("/perfil")}>

          <UserRound
            size={24}
            color="#5F6269"
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
  // ================= TELA =================

  container: {
    flex: 1,
    backgroundColor: "#FFF4AB",
  },

  scrollContent: {
    paddingBottom: 115,
  },

  // ================= HEADER =================

  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "flex-start",
    paddingHorizontal: 20,
    paddingTop: 20,
  },

  headerText: {
    flex: 1,
    paddingTop: 35,
  },

  greeting: {
    fontSize: 20,
    color: "#303037",
    fontFamily: "Poppins_400Regular",
  },

  title: {
    marginTop: 14,
    fontSize: 28,
    lineHeight: 34,
    color: "#202126",
    fontFamily: "Poppins_700Bold",
  },

  titlePet: {
    fontSize: 28,
    lineHeight: 34,
    color: "#EF3038",
    fontFamily: "Poppins_700Bold",
  },

  subtitle: {
    marginTop: 7,
    maxWidth: 260,
    fontSize: 13,
    lineHeight: 19,
    color: "#66666C",
    fontFamily: "Poppins_400Regular",
  },

  headerRight: {
    flexDirection: "row",
    alignItems: "flex-start",
  },

  notification: {
    width: 46,
    height: 46,
    borderRadius: 23,
    marginTop: 18,
    marginRight: 8,
    backgroundColor: "#EF3038",
    justifyContent: "center",
    alignItems: "center",

    elevation: 4,

    shadowColor: "#EF3038",
    shadowOffset: {
      width: 0,
      height: 3,
    },
    shadowOpacity: 0.22,
    shadowRadius: 5,
  },

  notificationBadge: {
    position: "absolute",
    top: 2,
    right: 2,
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: "#FFFFFF",
    borderWidth: 2,
    borderColor: "#EF3038",
  },

  logo: {
    width: 96,
    height: 96,
    resizeMode: "contain",
  },

  // ================= PROMOÇÃO =================

  promotionSection: {
    marginTop: 34,
    paddingHorizontal: 20,
  },

  sectionHeader: {
    marginBottom: 12,
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
  },

  sectionTitle: {
    fontSize: 21,
    color: "#202126",
    fontFamily: "Poppins_700Bold",
  },

  promotionShadow: {
    width: "100%",
    height: 205,
    borderRadius: 22,
    backgroundColor: "#FFFFFF",

    elevation: 7,

    shadowColor: "#6E642E",
    shadowOffset: {
      width: 0,
      height: 5,
    },
    shadowOpacity: 0.2,
    shadowRadius: 8,
  },

  promotionCard: {
    width: "100%",
    height: "100%",
    borderRadius: 22,
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
    left: 20,
    bottom: 20,
  },

  promotionTag: {
    alignSelf: "flex-start",
    marginBottom: 8,
    paddingVertical: 5,
    paddingHorizontal: 12,
    borderRadius: 20,
    backgroundColor: "#EF3038",
  },

  promotionTagText: {
    color: "#FFFFFF",
    fontSize: 11,
    fontFamily: "Poppins_600SemiBold",
  },

  promotionDiscount: {
    color: "#FFFFFF",
    fontSize: 27,
    lineHeight: 31,
    fontFamily: "Poppins_700Bold",
  },

  promotionDescription: {
    color: "#FFFFFF",
    fontSize: 15,
    fontFamily: "Poppins_600SemiBold",
  },

  dots: {
    marginTop: 12,
    flexDirection: "row",
    justifyContent: "center",
    gap: 6,
  },

  dot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    backgroundColor: "rgba(50,50,50,0.22)",
  },

  dotActive: {
    width: 20,
    backgroundColor: "#EF3038",
  },

  // ================= SERVIÇO =================

  serviceSection: {
    marginHorizontal: 20,
    marginTop: 26,
  },

  tituloServico: {
    marginBottom: 12,
    fontSize: 21,
    color: "#202126",
    fontFamily: "Poppins_700Bold",
  },

  appointmentCard: {
    minHeight: 92,
    backgroundColor: "#FFFBE1",
    borderRadius: 18,

    flexDirection: "row",
    alignItems: "center",

    paddingHorizontal: 14,
    paddingVertical: 12,

    borderWidth: 1,
    borderColor: "#E9D86D",
  },

  calendarIcon: {
    width: 55,
    height: 55,
    borderRadius: 18,
    backgroundColor: "#FFFFFF",
    justifyContent: "center",
    alignItems: "center",
  },

  appointmentInfo: {
    flex: 1,
    marginLeft: 13,
  },

  appointmentLabel: {
    fontSize: 12,
    color: "#797980",
    fontFamily: "Poppins_400Regular",
  },

  appointmentService: {
    marginTop: 1,
    fontSize: 16,
    color: "#202126",
    fontFamily: "Poppins_700Bold",
  },

  appointmentDate: {
    marginTop: 1,
    fontSize: 11,
    color: "#696970",
    fontFamily: "Poppins_400Regular",
  },

  // BOTÃO PRINCIPAL

  scheduleButton: {
    width: "100%",
    height: 58,
    marginTop: 14,

    backgroundColor: "#5d57fb",
    borderRadius: 17,

    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",

    elevation: 5,

    shadowColor: "#EF3038",
    shadowOffset: {
      width: 0,
      height: 4,
    },
    shadowOpacity: 0.25,
    shadowRadius: 7,
  },

  scheduleText: {
    marginHorizontal: 10,
    fontSize: 16,
    color: "#FFFFFF",
    fontFamily: "Poppins_700Bold",
  },

  // ================= MENU =================

  bottomMenu: {
    position: "absolute",
    bottom: 0,
    left: 0,
    right: 0,

    height: 82,

    backgroundColor: "#FFFFFF",

    flexDirection: "row",
    justifyContent: "space-around",
    alignItems: "center",

    borderTopLeftRadius: 22,
    borderTopRightRadius: 22,

    elevation: 15,

    shadowColor: "#000000",
    shadowOffset: {
      width: 0,
      height: -3,
    },
    shadowOpacity: 0.08,
    shadowRadius: 8,
  },

  menuItem: {
    height: 62,
    minWidth: 70,
    alignItems: "center",
    justifyContent: "center",
  },

  menuText: {
    marginTop: 3,
    fontSize: 11,
    fontFamily: "Poppins_400Regular",
  },

  menuTextActive: {
    marginTop: 3,
    fontSize: 11,
    color: "#EF3038",
    fontFamily: "Poppins_600SemiBold",
  },

  activeLine: {
    position: "absolute",
    bottom: 0,
    width: 28,
    height: 3,
    borderRadius: 5,
    backgroundColor: "#EF3038",
  },
});