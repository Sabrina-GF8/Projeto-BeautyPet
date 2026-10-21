import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from "react-native";
import { ArrowLeft, PawPrint } from "lucide-react-native";
import { useRouter } from "expo-router";
import { useEffect, useMemo, useState } from "react";
import { api } from "@/lib/api";

type Especie = { id: number; nome: string };
type Raca = { id: number; especie_id: number; nome: string };

function mascararData(valor: string) {
  const numeros = valor.replace(/\D/g, "").slice(0, 8);
  if (numeros.length <= 2) return numeros;
  if (numeros.length <= 4) return `${numeros.slice(0, 2)}/${numeros.slice(2)}`;
  return `${numeros.slice(0, 2)}/${numeros.slice(2, 4)}/${numeros.slice(4)}`;
}

function dataBrParaIso(valor: string) {
  if (!valor.trim()) return null;

  const correspondencia = valor.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
  if (!correspondencia) return undefined;

  const [, dia, mes, ano] = correspondencia;
  const data = new Date(Date.UTC(Number(ano), Number(mes) - 1, Number(dia)));

  if (
    data.getUTCFullYear() !== Number(ano) ||
    data.getUTCMonth() !== Number(mes) - 1 ||
    data.getUTCDate() !== Number(dia)
  ) {
    return undefined;
  }

  return `${ano}-${mes}-${dia}`;
}

export default function NovoPet() {
  const router = useRouter();
  const [nome, setNome] = useState("");
  const [cor, setCor] = useState("");
  const [dataNascimento, setDataNascimento] = useState("");
  const [sexo, setSexo] = useState("NAO_INFORMADO");
  const [castrado, setCastrado] = useState<boolean | null>(null);
  const [especies, setEspecies] = useState<Especie[]>([]);
  const [racas, setRacas] = useState<Raca[]>([]);
  const [especieId, setEspecieId] = useState<number | null>(null);
  const [racaId, setRacaId] = useState<number | null>(null);
  const [salvando, setSalvando] = useState(false);

  useEffect(() => {
    api<{ especies: Especie[]; racas: Raca[] }>("catalogo_pet")
      .then((resposta) => {
        const listaEspecies = resposta.especies || [];
        setEspecies(listaEspecies);
        setRacas(resposta.racas || []);

        const especiePadrao =
          listaEspecies.find((item) =>
            ["cão", "cao"].includes(item.nome.toLocaleLowerCase("pt-BR"))
          ) || listaEspecies[0];

        if (especiePadrao) setEspecieId(Number(especiePadrao.id));
      })
      .catch((erro) => {
        Alert.alert(
          "Novo pet",
          erro instanceof Error
            ? erro.message
            : "Não foi possível carregar as espécies."
        );
      });
  }, []);

  const racasDaEspecie = useMemo(
    () => racas.filter((raca) => Number(raca.especie_id) === especieId),
    [racas, especieId]
  );

  async function salvar() {
    if (!nome.trim()) {
      Alert.alert("Novo pet", "Informe pelo menos o nome do pet.");
      return;
    }

    const dataIso = dataBrParaIso(dataNascimento);
    if (dataIso === undefined) {
      Alert.alert("Novo pet", "Digite a data no formato DD/MM/AAAA.");
      return;
    }

    // HEURÍSTICA 9: data de nascimento no futuro é impossível (avisa antes de enviar).
    const agora = new Date();
    const hojeLocal = `${agora.getFullYear()}-${String(agora.getMonth() + 1).padStart(2, "0")}-${String(agora.getDate()).padStart(2, "0")}`;
    if (dataIso && dataIso > hojeLocal) {
      Alert.alert("Novo pet", "A data de nascimento não pode ser depois de hoje.");
      return;
    }

    try {
      setSalvando(true);
      const resposta = await api<{ mensagem?: string }>("adicionar_pet", {
        nome: nome.trim(),
        especie_id: especieId,
        raca_id: racaId,
        sexo,
        cor: cor.trim(),
        data_nascimento: dataIso,
        castrado,
      });

      Alert.alert(
        "Pet cadastrado",
        resposta.mensagem || "O pet foi vinculado à ficha do tutor.",
        [{ text: "OK", onPress: () => router.replace("/perfil") }]
      );
    } catch (erro) {
      Alert.alert(
        "Novo pet",
        erro instanceof Error ? erro.message : "Não foi possível cadastrar o pet."
      );
    } finally {
      setSalvando(false);
    }
  }

  return (
    <SafeAreaView style={styles.container}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollView
          contentContainerStyle={styles.content}
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={false}
        >
          <TouchableOpacity onPress={() => router.back()} style={styles.back}>
            <ArrowLeft size={24} color="#222222" />
          </TouchableOpacity>

          <View style={styles.titleRow}>
            <View style={styles.pawCircle}>
              <PawPrint size={24} color="#EF3038" />
            </View>
            <View>
              <Text style={styles.title}>Cadastrar pet</Text>
              <Text style={styles.subtitle}>
                Só o nome é obrigatório. Complete o restante quando quiser.
              </Text>
            </View>
          </View>

          <View style={styles.card}>
            <Text style={styles.label}>Nome *</Text>
            <TextInput
              style={styles.input}
              value={nome}
              onChangeText={setNome}
              placeholder="Ex: Max"
              maxLength={100}
            />

            <Text style={styles.label}>Espécie</Text>
            <View style={styles.chips}>
              {especies.map((especie) => {
                const selecionada = especieId === Number(especie.id);
                return (
                  <TouchableOpacity
                    key={especie.id}
                    style={[styles.chip, selecionada && styles.chipSelected]}
                    onPress={() => {
                      setEspecieId(Number(especie.id));
                      setRacaId(null);
                    }}
                  >
                    <Text style={selecionada ? styles.chipTextSelected : styles.chipText}>
                      {especie.nome}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>

            {racasDaEspecie.length > 0 && (
              <>
                <Text style={styles.label}>Raça (opcional)</Text>
                <ScrollView horizontal showsHorizontalScrollIndicator={false}>
                  <View style={styles.chipsLinha}>
                    {racasDaEspecie.map((raca) => {
                      const selecionada = racaId === Number(raca.id);
                      return (
                        <TouchableOpacity
                          key={raca.id}
                          style={[styles.chip, selecionada && styles.chipSelected]}
                          onPress={() => setRacaId(selecionada ? null : Number(raca.id))}
                        >
                          <Text style={selecionada ? styles.chipTextSelected : styles.chipText}>
                            {raca.nome}
                          </Text>
                        </TouchableOpacity>
                      );
                    })}
                  </View>
                </ScrollView>
              </>
            )}

            <Text style={styles.label}>Sexo (opcional)</Text>
            <View style={styles.chips}>
              {[
                { valor: "MACHO", texto: "Macho" },
                { valor: "FEMEA", texto: "Fêmea" },
                { valor: "NAO_INFORMADO", texto: "Não informar" },
              ].map((opcao) => {
                const selecionada = sexo === opcao.valor;
                return (
                  <TouchableOpacity
                    key={opcao.valor}
                    style={[styles.chip, selecionada && styles.chipSelected]}
                    onPress={() => setSexo(opcao.valor)}
                  >
                    <Text style={selecionada ? styles.chipTextSelected : styles.chipText}>
                      {opcao.texto}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>

            <Text style={styles.label}>Cor (opcional)</Text>
            <TextInput
              style={styles.input}
              value={cor}
              onChangeText={setCor}
              placeholder="Ex: Caramelo"
              maxLength={100}
            />

            <Text style={styles.label}>Nascimento (opcional)</Text>
            <TextInput
              style={styles.input}
              value={dataNascimento}
              onChangeText={(valor) => setDataNascimento(mascararData(valor))}
              placeholder="DD/MM/AAAA"
              keyboardType="numeric"
              maxLength={10}
            />

            <Text style={styles.label}>Castrado? (opcional)</Text>
            <View style={styles.chips}>
              {[
                { valor: true, texto: "Sim" },
                { valor: false, texto: "Não" },
              ].map((opcao) => {
                const selecionada = castrado === opcao.valor;
                return (
                  <TouchableOpacity
                    key={opcao.texto}
                    style={[styles.chip, selecionada && styles.chipSelected]}
                    onPress={() => setCastrado(selecionada ? null : opcao.valor)}
                  >
                    <Text style={selecionada ? styles.chipTextSelected : styles.chipText}>
                      {opcao.texto}
                    </Text>
                  </TouchableOpacity>
                );
              })}
            </View>

            <TouchableOpacity
              style={[styles.button, salvando && styles.buttonDisabled]}
              onPress={salvar}
              disabled={salvando}
            >
              <Text style={styles.buttonText}>
                {salvando ? "Cadastrando..." : "Cadastrar e vincular"}
              </Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: "#FFF59D" },
  flex: { flex: 1 },
  content: { paddingHorizontal: 20, paddingTop: 24, paddingBottom: 40 },
  back: { width: 40, height: 40, justifyContent: "center" },
  titleRow: { flexDirection: "row", alignItems: "center", marginVertical: 14 },
  pawCircle: { width: 46, height: 46, borderRadius: 23, backgroundColor: "#FFFFFF", alignItems: "center", justifyContent: "center", marginRight: 12 },
  title: { fontFamily: "Poppins_600SemiBold", fontSize: 20, color: "#222222" },
  subtitle: { fontFamily: "Poppins_400Regular", fontSize: 11, color: "#555555", maxWidth: 270 },
  card: { backgroundColor: "#FFFFFF", borderRadius: 14, padding: 16, elevation: 2 },
  label: { fontFamily: "Poppins_500Medium", fontSize: 12, color: "#333333", marginTop: 14, marginBottom: 7 },
  input: { height: 46, borderWidth: 1, borderColor: "#DDDDDD", borderRadius: 10, backgroundColor: "#FAFAFA", paddingHorizontal: 12, fontFamily: "Poppins_400Regular", color: "#222222" },
  chips: { flexDirection: "row", flexWrap: "wrap", gap: 8 },
  chipsLinha: { flexDirection: "row", gap: 8, paddingRight: 8 },
  chip: { borderRadius: 18, backgroundColor: "#F4F4F4", borderWidth: 1, borderColor: "#E7E7E7", paddingHorizontal: 12, paddingVertical: 8 },
  chipSelected: { backgroundColor: "#EF3038", borderColor: "#EF3038" },
  chipText: { fontFamily: "Poppins_400Regular", fontSize: 11, color: "#333333" },
  chipTextSelected: { fontFamily: "Poppins_600SemiBold", fontSize: 11, color: "#FFFFFF" },
  button: { height: 50, borderRadius: 11, backgroundColor: "#EF3038", alignItems: "center", justifyContent: "center", marginTop: 26 },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { fontFamily: "Poppins_600SemiBold", fontSize: 14, color: "#FFFFFF" },
});
