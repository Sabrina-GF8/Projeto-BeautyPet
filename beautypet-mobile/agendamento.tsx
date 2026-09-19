import {
    Alert,
    SafeAreaView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from "react-native";

import { useRouter } from "expo-router";
import {
    ArrowLeft,
    Clock,
} from "lucide-react-native";

import { useState } from "react";

import { Calendar } from "react-native-calendars";

export default function Agendamento() {

  const router = useRouter();

  const [diaSelecionado, setDiaSelecionado] = useState("");
  const [horarioSelecionado, setHorarioSelecionado] = useState("");

  const horarios = [
    "09:00",
    "11:00",
    "14:00",
    "10:00",
    "12:00",
    "16:00",
  ];

  function confirmarAgendamento() {

    if (diaSelecionado === "") {
      Alert.alert(
        "Atenção",
        "Escolha uma data."
      );
      return;
    }

    if (horarioSelecionado === "") {
      Alert.alert(
        "Atenção",
        "Escolha um horário."
      );
      return;
    }

    Alert.alert(
      "Agendamento confirmado!",
      `Data: ${diaSelecionado}\nHorário: ${horarioSelecionado}`,
      [
        {
          text: "OK",
          onPress: () => router.back(),
        },
      ]
    );
  }

  return (
    <SafeAreaView style={styles.container}>

      {/* CABEÇALHO */}

      <View style={styles.header}>

        <TouchableOpacity
          onPress={() => router.back()}
        >
          <ArrowLeft
            size={24}
            color="#222222"
          />
        </TouchableOpacity>

        <View style={styles.headerText}>

          <Text style={styles.title}>
            Agendar data e horário
          </Text>

          <Text style={styles.subtitle}>
            Escolha o melhor dia e horário
          </Text>

        </View>

      </View>


      {/* CALENDÁRIO */}

      <View style={styles.calendarContainer}>

        <Calendar
          current="2026-06-01"

          onDayPress={(day) => {
            setDiaSelecionado(day.dateString);
          }}

          markedDates={{
            [diaSelecionado]: {
              selected: true,
              selectedColor: "#FFE16B",
              selectedTextColor: "#222222",
            },
          }}

          theme={{
            backgroundColor: "#FFFFFF",
            calendarBackground: "#FFFFFF",

            textSectionTitleColor: "#333333",
            dayTextColor: "#333333",
            textDisabledColor: "#CCCCCC",

            monthTextColor: "#222222",
            arrowColor: "#222222",

            textMonthFontFamily: "Poppins_600SemiBold",
            textDayFontFamily: "Poppins_400Regular",
            textDayHeaderFontFamily: "Poppins_500Medium",

            textMonthFontSize: 11,
            textDayFontSize: 9,
            textDayHeaderFontSize: 7,

            selectedDayBackgroundColor: "#FFE16B",
            selectedDayTextColor: "#222222",
          }}

          enableSwipeMonths={true}

        />

      </View>


      {/* HORÁRIOS */}

      <View style={styles.scheduleArea}>

        <View style={styles.scheduleTitle}>

          <Clock
            size={16}
            color="#333333"
          />

          <Text style={styles.scheduleText}>
            Horários disponíveis
          </Text>

        </View>


        <View style={styles.timesContainer}>

          {horarios.map((horario) => {

            const selecionado =
              horario === horarioSelecionado;

            return (
              <TouchableOpacity
                key={horario}
                style={[
                  styles.timeButton,
                  selecionado &&
                    styles.timeSelected,
                ]}
                onPress={() =>
                  setHorarioSelecionado(horario)
                }
              >

                <Text
                  style={[
                    styles.timeText,
                    selecionado &&
                      styles.timeTextSelected,
                  ]}
                >
                  {horario}
                </Text>

              </TouchableOpacity>
            );

          })}

        </View>

      </View>


      {/* BOTÃO CONFIRMAR */}

      <TouchableOpacity
        style={styles.confirmButton}
        onPress={confirmarAgendamento}
      >

        <Text style={styles.confirmText}>
          Confirmar
        </Text>

      </TouchableOpacity>

    </SafeAreaView>
  );
}


const styles = StyleSheet.create({

  container: {
    flex: 1,
    backgroundColor: "#FFFFFF",
    paddingHorizontal: 22,
  },

  header: {
    flexDirection: "row",
    alignItems: "flex-start",
    marginTop: 20,
  },

  headerText: {
    marginLeft: 12,
  },

  title: {
    fontSize: 15,
    color: "#222222",
    fontFamily: "Poppins_500Medium",
  },

  subtitle: {
    fontSize: 9,
    color: "#555555",
    marginTop: 4,
    fontFamily: "Poppins_400Regular",
  },

  calendarContainer: {
    backgroundColor: "#FFFFFF",
    marginTop: 18,
    borderRadius: 10,
    elevation: 3,
    padding: 5,
  },

  scheduleArea: {
    marginTop: 18,
  },

  scheduleTitle: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 8,
  },

  scheduleText: {
    fontSize: 10,
    color: "#333333",
    marginLeft: 5,
    fontFamily: "Poppins_500Medium",
  },

  timesContainer: {
    flexDirection: "row",
    flexWrap: "wrap",
    justifyContent: "space-between",
  },

  timeButton: {
    width: "30%",
    height: 27,
    backgroundColor: "#D9D9D9",
    borderRadius: 15,
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 8,
  },

  timeSelected: {
    backgroundColor: "#FFE16B",
  },

  timeText: {
    fontSize: 8,
    color: "#333333",
    fontFamily: "Poppins_400Regular",
  },

  timeTextSelected: {
    fontFamily: "Poppins_600SemiBold",
  },

  confirmButton: {
    height: 45,
    backgroundColor: "#FFE16B",
    borderRadius: 10,
    justifyContent: "center",
    alignItems: "center",
    marginTop: "auto",
    marginBottom: 20,
    elevation: 2,
  },

  confirmText: {
    fontSize: 13,
    color: "#222222",
    fontFamily: "Poppins_600SemiBold",
  },

});