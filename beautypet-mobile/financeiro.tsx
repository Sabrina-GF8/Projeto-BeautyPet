import {
    SafeAreaView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";

import {
    ArrowRight,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    Home,
    Plus,
    UserRound,
    Wallet
} from "lucide-react-native";

import { useRouter } from "expo-router";

export default function Financeiro() {
  const router = useRouter();

  return (
    <SafeAreaView style={styles.container}>

      <View style={styles.content}>

        {/* CABEÇALHO */}
        <View style={styles.header}>

          <View>
            <Text style={styles.title}>
              Financeiro
            </Text>

            <Text style={styles.subtitle}>
              Controle seus gastos com o Max
            </Text>
          </View>

          {/* BOTÃO + */}
          <TouchableOpacity
            style={styles.addButton}
            onPress={() => {
              // Depois vamos colocar a navegação
              // para a tela de adicionar gasto aqui
            }}
          >
            <Plus
              size={22}
              color="#333333"
            />
          </TouchableOpacity>

        </View>


        {/* SELETOR DE MÊS */}
        <View style={styles.monthSelector}>

          <TouchableOpacity>
            <ChevronLeft
              size={18}
              color="#B5B5B5"
            />
          </TouchableOpacity>

          <Text style={styles.monthText}>
            Junho 2026
          </Text>

          <TouchableOpacity>
            <ChevronRight
              size={18}
              color="#B5B5B5"
            />
          </TouchableOpacity>

        </View>


        {/* TOTAL DO MÊS */}
        <View style={styles.totalCard}>

          <Text style={styles.totalLabel}>
            Total do mês
          </Text>

          <Text style={styles.totalValue}>
            R$ 225,00
          </Text>

        </View>


        {/* CARD DO GASTO */}
        <TouchableOpacity style={styles.expenseCard}>

          <View style={styles.expenseIcon}>
            <Text style={styles.star}>
              ★
            </Text>
          </View>

          <View style={styles.expenseInfo}>

            <Text style={styles.expenseCompany}>
              BeautyPet
            </Text>

            <Text style={styles.expenseValue}>
              R$ 50,00
            </Text>

            <Text style={styles.expenseDescription}>
              1 serviço em Junho
            </Text>

          </View>

          <ArrowRight
            size={18}
            color="#FFFFFF"
          />

        </TouchableOpacity>


        {/* ÁREA DO GRÁFICO */}
        <View style={styles.chartCard}>

          <Text style={styles.chartTitle}>
            Gastos por categoria
          </Text>


          {/* ESPAÇO RESERVADO PARA O GRÁFICO */}
          <View style={styles.chartPlaceholder}>

            <View style={styles.chartCircle}>

              <Text style={styles.chartTotalLabel}>
                total
              </Text>

              <Text style={styles.chartTotal}>
                R$ 225,00
              </Text>

            </View>

          </View>


          {/* LEGENDA */}
          <View style={styles.legend}>

            <View style={styles.legendColumn}>

              <View style={styles.legendItem}>
                <View
                  style={[
                    styles.legendDot,
                    { backgroundColor: "#FF5A5F" },
                  ]}
                />

                <View>
                  <Text style={styles.legendTitle}>
                    Higiene
                  </Text>

                  <Text style={styles.legendValue}>
                    R$ 50,00
                  </Text>
                </View>
              </View>


              <View style={styles.legendItem}>
                <View
                  style={[
                    styles.legendDot,
                    { backgroundColor: "#62C9AA" },
                  ]}
                />

                <View>
                  <Text style={styles.legendTitle}>
                    Saúde
                  </Text>

                  <Text style={styles.legendValue}>
                    R$ 55,00
                  </Text>
                </View>
              </View>

            </View>


            <View style={styles.legendColumn}>

              <View style={styles.legendItem}>
                <View
                  style={[
                    styles.legendDot,
                    { backgroundColor: "#FFAD43" },
                  ]}
                />

                <View>
                  <Text style={styles.legendTitle}>
                    Alimentação
                  </Text>

                  <Text style={styles.legendValue}>
                    R$ 120,00
                  </Text>
                </View>
              </View>

            </View>

          </View>

        </View>

      </View>


      {/* MENU INFERIOR */}
      <View style={styles.bottomMenu}>

        <TouchableOpacity style={styles.menuItem}
         onPress={() => router.push("/home")}>
          <Home
            size={21}
            color="#999999"
          />

          <Text style={styles.menuText}>
            Home
          </Text>
        </TouchableOpacity>


        <TouchableOpacity style={styles.menuItem}>
          <CalendarDays
            size={21}
            color="#999999"
          />

          <Text style={styles.menuText}>
            Agenda
          </Text>
        </TouchableOpacity>


        <TouchableOpacity style={styles.menuItem}>
          <Wallet
            size={21}
            color="#FF4545"
          />

          <Text
            style={[
              styles.menuText,
              styles.menuSelected,
            ]}
          >
            Financeiro
          </Text>
        </TouchableOpacity>


        <TouchableOpacity style={styles.menuItem}>
          <UserRound
            size={21}
            color="#999999"
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
    backgroundColor: "#FFFFFF",
  },

  content: {
    flex: 1,
    backgroundColor: "#faf19f",
    paddingHorizontal: "5%",
    paddingTop: "3%",
  },


  /* CABEÇALHO */

  header: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
  },

  title: {
    marginTop:40,
    fontSize: 18,
    color: "#222222",
    fontFamily: "Poppins_600SemiBold",
  },

  subtitle: {
    fontSize: 12,
    color: "#666666",
    marginTop: 2,
    fontFamily: "Poppins_400Regular",
  },

  addButton: {
    width: 42,
    height: 42,
    borderRadius: 21,
    backgroundColor: "#FFFFFF",
    justifyContent: "center",
    alignItems: "center",

    elevation: 3,

    shadowColor: "#000000",
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.15,
    shadowRadius: 3,
  },


  /* MÊS */

  monthSelector: {
    width: "100%",
    height: 32,
    backgroundColor: "#FFFFFF",
    borderRadius: 12,

    marginTop: 14,

    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",

    paddingHorizontal: 12,

    elevation: 1,
  },

  monthText: {
    fontSize: 12,
    color: "#222222",
    fontFamily: "Poppins_500Medium",
  },


  /* TOTAL */

  totalCard: {
    width: "100%",
    backgroundColor: "#FFFFFF",
    borderRadius: 12,

    paddingHorizontal: 12,
    paddingVertical: 11,

    marginTop: 14,
  },

  totalLabel: {
    fontSize: 10,
    color: "#777777",
    fontFamily: "Poppins_400Regular",
  },

  totalValue: {
    fontSize: 16,
    color: "#222222",
    marginTop: 3,
    fontFamily: "Poppins_500Medium",
  },


  /* GASTO */

  expenseCard: {
    width: "100%",
    height: 68,

    backgroundColor: "#FF5A5F",
    borderRadius: 11,

    marginTop: 12,

    flexDirection: "row",
    alignItems: "center",

    paddingHorizontal: 10,
  },

  expenseIcon: {
    width: 36,
    height: 36,
    borderRadius: 9,

    backgroundColor: "rgba(255,255,255,0.25)",

    justifyContent: "center",
    alignItems: "center",
  },

  star: {
    color: "#FFFFFF",
    fontSize: 24,
  },

  expenseInfo: {
    flex: 1,
    marginLeft: 9,
  },

  expenseCompany: {
    color: "#FFFFFF",
    fontSize: 10,
    fontFamily: "Poppins_400Regular",
  },

  expenseValue: {
    color: "#FFFFFF",
    fontSize: 12,
    marginTop: 1,
    fontFamily: "Poppins_500Medium",
  },

  expenseDescription: {
    color: "#FFFFFF",
    fontSize: 10,
    marginTop: 1,
    fontFamily: "Poppins_400Regular",
  },


  /* GRÁFICO */

  chartCard: {
    width: "100%",
    flex: 1,

    backgroundColor: "#FFFFFF",
    borderRadius: 12,

    marginTop: 12,
    marginBottom: 12,

    paddingHorizontal: 12,
    paddingTop: 12,
  },

  chartTitle: {
    fontSize: 13,
    color: "#222222",
    fontFamily: "Poppins_500Medium",
  },

  chartPlaceholder: {
    flex: 1,

    justifyContent: "center",
    alignItems: "center",

    maxHeight: 190,
  },

  chartCircle: {
    width: 130,
    aspectRatio: 1,

    borderRadius: 64,

    borderWidth: 22,
    borderColor: "#FFAD43",

    justifyContent: "center",
    alignItems: "center",
  },

  chartTotalLabel: {
    fontSize: 7,
    color: "#999999",
    fontFamily: "Poppins_400Regular",
  },

  chartTotal: {
    fontSize: 11,
    color: "#333333",
    fontFamily: "Poppins_500Medium",
  },


  /* LEGENDA */

  legend: {
    flexDirection: "row",
    justifyContent: "space-between",

    paddingBottom: 12,
  },

  legendColumn: {
    width: "48%",
  },

  legendItem: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 9,
  },

  legendDot: {
    width: 7,
    height: 7,
    borderRadius: 4,
    marginRight: 6,
  },

  legendTitle: {
    fontSize: 12,
    color: "#555555",
    fontFamily: "Poppins_500Medium",
  },

  legendValue: {
    fontSize: 6,
    color: "#999999",
    fontFamily: "Poppins_400Regular",
  },


  /* MENU INFERIOR */

  bottomMenu: {
    height: 62,

    backgroundColor: "#FFFFFF",

    borderTopWidth: 1,
    borderTopColor: "#EEEEEE",

    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-around",
  },

  menuItem: {
    alignItems: "center",
    justifyContent: "center",
    width: 70,
  },

  menuText: {
    fontSize: 12,
    color: "#999999",
    marginTop: 3,
    fontFamily: "Poppins_400Regular",
  },

  menuSelected: {
    color: "#FF4545",
    fontFamily: "Poppins_500Medium",
  },

});