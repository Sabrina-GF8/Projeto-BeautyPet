import {
    Alert,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View
} from "react-native";

import { useLocalSearchParams, useRouter } from "expo-router";
import { ArrowLeft, Bone, Home, Scissors, ShowerHead, Stethoscope } from "lucide-react-native";
import { useEffect, useState } from "react";
import { api } from "../lib/api";

export default function Servicos() {

  const router = useRouter();
  const params = useLocalSearchParams<{ petId?: string }>();

  const [servicosSelecionados, setServicosSelecionados] = useState<string[]>([]);
  const [servicosBanco, setServicosBanco] = useState<
    { id: number; nome: string; descricao?: string | null; categoria: string }[]
  >([]);

  useEffect(() => {
    api<{
      servicos: { id: number; nome: string; descricao?: string | null; categoria: string }[];
    }>("servicos")
      .then((resposta) => setServicosBanco(resposta.servicos || []))
      .catch(() => setServicosBanco([]));
  }, []);

  function encontrarServicoId(rotulo: string) {
    const normalizar = (valor: string) =>
      valor
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();

    const lista = servicosBanco.map((servico) => ({
      ...servico,
      nomeNormalizado: normalizar(servico.nome),
      categoriaNormalizada: normalizar(servico.categoria),
    }));

    // IMPORTANTE:
    // Cada opção abaixo procura o serviço EXATO correspondente no banco.
    // Assim "Banho e Tosa" nunca cai em "Tosa higiênica".

    if (rotulo === "Banho") {
      return lista.find((s) => s.nomeNormalizado === "banho")?.id;
    }

    if (rotulo === "Banho e Tosa") {
      return lista.find(
        (s) =>
          s.nomeNormalizado === "banho e tosa" ||
          (
            s.nomeNormalizado.includes("banho") &&
            s.nomeNormalizado.includes("tosa") &&
            !s.nomeNormalizado.includes("higien")
          )
      )?.id;
    }

    if (rotulo === "Tosa Higiênica") {
      return lista.find(
        (s) =>
          s.nomeNormalizado === "tosa higienica" ||
          s.nomeNormalizado.includes("tosa higien")
      )?.id;
    }

    if (rotulo === "Consulta") {
      return lista.find(
        (s) =>
          s.nomeNormalizado.includes("consulta") ||
          s.categoriaNormalizada.includes("veterin")
      )?.id;
    }

    if (rotulo === "Hotel") {
      return lista.find((s) => s.categoriaNormalizada.includes("hotel"))?.id;
    }

    if (rotulo === "Creche") {
      return lista.find((s) => s.categoriaNormalizada.includes("creche"))?.id;
    }

    return undefined;
  }

  function avancar() {
    const petId = Number(params.petId);

    if (!petId) {
      Alert.alert(
        "Agendamento",
        "Selecione o pet antes de escolher os serviços.",
        [{ text: "OK", onPress: () => router.replace("/escolher_pet") }]
      );
      return;
    }

    if (servicosSelecionados.length === 0) {
      Alert.alert("Agendamento", "Selecione pelo menos um serviço.");
      return;
    }

    const ids = servicosSelecionados
      .map(encontrarServicoId)
      .filter((id): id is number => Boolean(id));

    if (ids.length !== servicosSelecionados.length) {
      Alert.alert(
        "Agendamento",
        "Um dos serviços selecionados não está disponível no cadastro web."
      );
      return;
    }

    router.push({
      pathname: "/agendamento",
      params: {
        petId: String(petId),
        servicos: ids.join(","),
      },
    });
  }

  function selecionarServico(servico: string) {

    if (servicosSelecionados.includes(servico)) {

      setServicosSelecionados(
        servicosSelecionados.filter((item) => item !== servico)
      );

    } else {

      setServicosSelecionados([
        ...servicosSelecionados,
        servico,
      ]);

    }
  }

  return (
    <SafeAreaView style={styles.container}>

      {/* CABEÇALHO */}

      <View style={styles.header}>

        <TouchableOpacity onPress={() => router.back()}>
          <ArrowLeft
            size={24}
            color="#222222"
          />
        </TouchableOpacity>

        <View style={styles.headerText}>

          <Text style={styles.title}>
            Quais serviços você deseja
          </Text>

          <Text style={styles.textSubtitle}>
            agendar no BeautyPet?
          </Text>

          <Text style={styles.subtitle}>
            Selecione o melhor para o seu pet
          </Text>

        </View>

      </View>

      <ScrollView
        style={styles.listaRolavel}
        contentContainerStyle={styles.listaRolavelConteudo}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.serviceList}>

          {/* BANHO */}

          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => selecionarServico("Banho")}
          >

            <View style={[styles.serviceIcon, styles.banho]}>
              <ShowerHead
                size={25}
                color="#333333"
              />
            </View>

            <View style={styles.serviceText}>
              <Text style={styles.serviceName}>
                Banho
              </Text>

              <Text style={styles.serviceDescription}>
                Produtos premium
              </Text>
            </View>

            <View
              style={[
                styles.checkbox,
                servicosSelecionados.includes("Banho") &&
                  styles.checkboxSelecionado,
              ]}
            >
              {servicosSelecionados.includes("Banho") && (
                <Text style={styles.check}>
                  ✓
                </Text>
              )}
            </View>

          </TouchableOpacity>


          {/* BANHO E TOSA */}

          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => selecionarServico("Banho e Tosa")}
          >

            <View style={[styles.serviceIcon, styles.tosa]}>
              <Scissors
                size={25}
                color="#333333"
              />
            </View>

            <View style={styles.serviceText}>
              <Text style={styles.serviceName}>
                Banho e Tosa
              </Text>

              <Text style={styles.serviceDescription}>
                Banho com tosa completa
              </Text>
            </View>

            <View
              style={[
                styles.checkbox,
                servicosSelecionados.includes("Banho e Tosa") &&
                  styles.checkboxSelecionado,
              ]}
            >
              {servicosSelecionados.includes("Banho e Tosa") && (
                <Text style={styles.check}>
                  ✓
                </Text>
              )}
            </View>

          </TouchableOpacity>


          {/* TOSA HIGIÊNICA */}

          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => selecionarServico("Tosa Higiênica")}
          >

            <View style={[styles.serviceIcon, styles.tosa]}>
              <Scissors
                size={25}
                color="#333333"
              />
            </View>

            <View style={styles.serviceText}>
              <Text style={styles.serviceName}>
                Tosa Higiênica
              </Text>

              <Text style={styles.serviceDescription}>
                Higiene e aparação das regiões necessárias
              </Text>
            </View>

            <View
              style={[
                styles.checkbox,
                servicosSelecionados.includes("Tosa Higiênica") &&
                  styles.checkboxSelecionado,
              ]}
            >
              {servicosSelecionados.includes("Tosa Higiênica") && (
                <Text style={styles.check}>
                  ✓
                </Text>
              )}
            </View>

          </TouchableOpacity>


          {/* CONSULTA */}

          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => selecionarServico("Consulta")}
          >

            <View style={[styles.serviceIcon, styles.consulta]}>
              <Stethoscope
                size={25}
                color="#333333"
              />
            </View>

            <View style={styles.serviceText}>
              <Text style={styles.serviceName}>
                Consulta
              </Text>

              <Text style={styles.serviceDescription}>
                Veterinária
              </Text>
            </View>

            <View
              style={[
                styles.checkbox,
                servicosSelecionados.includes("Consulta") &&
                  styles.checkboxSelecionado,
              ]}
            >
              {servicosSelecionados.includes("Consulta") && (
                <Text style={styles.check}>
                  ✓
                </Text>
              )}
            </View>

          </TouchableOpacity>


          {/* HOTEL */}

          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => selecionarServico("Hotel")}
          >

            <View style={[styles.serviceIcon, styles.hotel]}>
              <Home
                size={25}
                color="#333333"
              />
            </View>

            <View style={styles.serviceText}>
              <Text style={styles.serviceName}>
                Hotel
              </Text>

              <Text style={styles.serviceDescription}>
                Hospedagem confortável
              </Text>
            </View>

            <View
              style={[
                styles.checkbox,
                servicosSelecionados.includes("Hotel") &&
                  styles.checkboxSelecionado,
              ]}
            >
              {servicosSelecionados.includes("Hotel") && (
                <Text style={styles.check}>
                  ✓
                </Text>
              )}
            </View>

          </TouchableOpacity>


          {/* CRECHE */}

          <TouchableOpacity
            style={styles.serviceCard}
            onPress={() => selecionarServico("Creche")}
          >

            <View style={[styles.serviceIcon, styles.creche]}>
              <Bone
                size={25}
                color="#333333"
              />
            </View>

            <View style={styles.serviceText}>
              <Text style={styles.serviceName}>
                Creche
              </Text>

              <Text style={styles.serviceDescription}>
                Diversão para o seu pet
              </Text>
            </View>

            <View
              style={[
                styles.checkbox,
                servicosSelecionados.includes("Creche") &&
                  styles.checkboxSelecionado,
              ]}
            >
              {servicosSelecionados.includes("Creche") && (
                <Text style={styles.check}>
                  ✓
                </Text>
              )}
            </View>

          </TouchableOpacity>

        </View>


        {/* BOTÃO AVANÇAR */}

        <TouchableOpacity
          style={styles.avancarButton}
          onPress={avancar}
        >

          <Text style={styles.avancarText}>
            Avançar
          </Text>

        </TouchableOpacity>
      </ScrollView>

    </SafeAreaView>
  );
}


const styles = StyleSheet.create({

  container: {
    flex: 1,
    backgroundColor: "#FFF4A8",
    paddingHorizontal: 22,
  },

  header: {
    flexDirection: "row",
    alignItems: "flex-start",
    marginTop: 15,
  },

  headerText: {
    marginLeft: 6,
  },

  title: {
    fontSize: 20,
    marginTop: 50,
    color: "#222222",
    fontFamily: "Poppins_500Medium",
  },
  textSubtitle: {
  marginTop: 2,
  fontSize: 20,
  fontFamily: "Poppins_500Medium",
  },

  subtitle: {
    fontSize: 15,
    color: "#555555",
    marginTop: 5,
    fontFamily: "Poppins_400Regular",
  },

  listaRolavel: {
    flex: 1,
  },

  listaRolavelConteudo: {
    paddingBottom: 30,
  },

  serviceList: {
    marginTop: 25,
  },

  serviceCard: {
    height: 85,
    backgroundColor: "#FFFFFF",
    borderRadius: 10,
    marginBottom: 12,
    paddingHorizontal: 10,
    flexDirection: "row",
    alignItems: "center",
    elevation: 2,
  },

  serviceIcon: {
    width: 38,
    height: 38,
    borderRadius: 10,
    justifyContent: "center",
    alignItems: "center",
  },

  banho: {
    backgroundColor: "#BEE8FF",
    width: 50,
    height: 50,
  },

  consulta: {
    backgroundColor: "#A8FF9D",
    width: 50,
    height: 50,
  },

  tosa: {
    backgroundColor: "#FFD7D7",
    width: 50,
    height: 50,
  },

  hotel: {
    backgroundColor: "#E8D6FF",
    width: 50,
    height: 50,
  },

  creche: {
    backgroundColor: "#D9D9FF",
    width: 50,
    height: 50,
  },

  serviceText: {
    flex: 1,
    marginLeft: 10,
  },

  serviceName: {
    fontSize: 17,
    color: "#222222",
    fontFamily: "Poppins_500Medium",
  },

  serviceDescription: {
    fontSize: 13,
    color: "#555555",
    fontFamily: "Poppins_400Regular",
  },

  checkbox: {
    width: 20,
    height: 20,
    borderWidth: 1,
    borderColor: "#BBBBBB",
    borderRadius: 2,
    justifyContent: "center",
    alignItems: "center",
  },

  checkboxSelecionado: {
    backgroundColor: "#07219E",
    borderColor: "#07219E",
  },

  check: {
    color: "#FFFFFF",
    fontSize: 11,
    fontWeight: "bold",
  },

  avancarButton: {
    height: 55,
    backgroundColor: "#ffccaa",
    borderRadius: 10,
    justifyContent: "center",
    alignItems: "center",
    marginTop: 35,
    elevation: 2,
  },

  avancarText: {
    fontSize: 15,
    color: "#222222",
    fontFamily: "Poppins_600SemiBold",
  },
});