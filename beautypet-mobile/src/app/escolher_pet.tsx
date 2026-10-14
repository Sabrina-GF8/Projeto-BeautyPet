import {
  Alert,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from "react-native";

import { PawPrint, ArrowLeft } from "lucide-react-native";
import { useRouter } from "expo-router";
import { useEffect, useState } from "react";
import { api } from "../lib/api";

type Pet = {
  id: number;
  nome: string;
  raca?: string | null;
};

export default function EscolherPet() {
  const router = useRouter();
  const [pets, setPets] = useState<Pet[]>([]);
  const [petSelecionado, setPetSelecionado] = useState<number | null>(null);

  useEffect(() => {
    api<{ pets: Pet[] }>("meus_pets")
      .then((resposta) => {
        const lista = resposta.pets || [];
        setPets(lista);

        if (lista.length === 1) {
          setPetSelecionado(Number(lista[0].id));
        }
      })
      .catch((erro) => {
        Alert.alert(
          "Agendamento",
          erro instanceof Error
            ? erro.message
            : "Não foi possível carregar seus pets."
        );
      });
  }, []);

  function confirmar() {
    if (!petSelecionado) {
      Alert.alert("Agendamento", "Selecione o pet que será atendido.");
      return;
    }

    router.push({
      pathname: "/servicos",
      params: {
        petId: String(petSelecionado),
      },
    });
  }

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.content}
        showsVerticalScrollIndicator={false}
      >
        <TouchableOpacity onPress={() => router.back()} style={styles.back}>
          <ArrowLeft size={22} color="#222222" />
        </TouchableOpacity>

        <Text style={styles.title}>Qual pet?</Text>
        <Text style={styles.subtitle}>
          Qual pet você deseja agendar no Beauty Pet?
        </Text>

        <View style={styles.card}>
          {pets.length === 0 ? (
            <Text style={styles.empty}>
              Nenhum pet está vinculado à sua ficha no sistema web.
            </Text>
          ) : (
            pets.map((pet) => {
              const selecionado = petSelecionado === Number(pet.id);

              return (
                <TouchableOpacity
                  key={pet.id}
                  style={[
                    styles.petRow,
                    selecionado && styles.petRowSelected,
                  ]}
                  onPress={() => setPetSelecionado(Number(pet.id))}
                  activeOpacity={0.8}
                >
                  <View style={styles.petIcon}>
                    <PawPrint size={20} color="#333333" />
                  </View>

                  <View style={styles.petText}>
                    <Text style={styles.petName}>{pet.nome}</Text>
                    <Text style={styles.petBreed}>
                      {pet.raca || "Pet Beauty Pet"}
                    </Text>
                  </View>

                  <View
                    style={[
                      styles.radio,
                      selecionado && styles.radioSelected,
                    ]}
                  >
                    {selecionado && <View style={styles.radioDot} />}
                  </View>
                </TouchableOpacity>
              );
            })
          )}
        </View>

        <TouchableOpacity
          style={styles.confirmButton}
          onPress={confirmar}
          activeOpacity={0.85}
        >
          <Text style={styles.confirmText}>Confirmar</Text>
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#FFF59D",
  },

  content: {
    paddingHorizontal: 22,
    paddingTop: 24,
    paddingBottom: 40,
  },

  back: {
    width: 38,
    height: 38,
    justifyContent: "center",
  },

  title: {
    fontFamily: "Poppins_600SemiBold",
    fontSize: 20,
    color: "#222222",
    marginTop: 8,
  },

  subtitle: {
    fontFamily: "Poppins_400Regular",
    fontSize: 12,
    color: "#555555",
    marginTop: 4,
    marginBottom: 20,
  },

  card: {
    backgroundColor: "#FFFFFF",
    borderRadius: 13,
    padding: 10,
    elevation: 2,
  },

  petRow: {
    minHeight: 62,
    borderRadius: 11,
    paddingHorizontal: 11,
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 8,
    borderWidth: 1,
    borderColor: "#EEEEEE",
  },

  petRowSelected: {
    borderColor: "#EF3038",
    backgroundColor: "#FFF9F9",
  },

  petIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: "#FADE84",
    justifyContent: "center",
    alignItems: "center",
  },

  petText: {
    flex: 1,
    marginLeft: 12,
  },

  petName: {
    fontFamily: "Poppins_600SemiBold",
    fontSize: 14,
    color: "#222222",
  },

  petBreed: {
    fontFamily: "Poppins_400Regular",
    fontSize: 10,
    color: "#777777",
    marginTop: 2,
  },

  radio: {
    width: 20,
    height: 20,
    borderRadius: 10,
    borderWidth: 1.5,
    borderColor: "#BBBBBB",
    justifyContent: "center",
    alignItems: "center",
  },

  radioSelected: {
    borderColor: "#EF3038",
  },

  radioDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    backgroundColor: "#EF3038",
  },

  empty: {
    fontFamily: "Poppins_400Regular",
    fontSize: 12,
    color: "#777777",
    textAlign: "center",
    paddingVertical: 25,
  },

  confirmButton: {
    marginTop: 22,
    height: 48,
    borderRadius: 12,
    backgroundColor: "#FFE16B",
    justifyContent: "center",
    alignItems: "center",
    elevation: 2,
  },

  confirmText: {
    fontFamily: "Poppins_600SemiBold",
    color: "#222222",
    fontSize: 14,
  },
});
