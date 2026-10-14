import { api } from "@/lib/api";
import { useLocalSearchParams, useRouter } from "expo-router";
import { ArrowLeft } from "lucide-react-native";
import { useEffect, useState } from "react";
import { SafeAreaView, ScrollView, StyleSheet, Text, TouchableOpacity, View } from "react-native";

type Item = { data_fechamento: string; pet: string; servico: string; descricao?: string | null; valor_total: number };

export default function Budget() {
  const router = useRouter();
  const params = useLocalSearchParams<{ ano?: string; mes?: string }>();
  const [itens, setItens] = useState<Item[]>([]);
  const ano = Number(params.ano || new Date().getFullYear());
  const mes = Number(params.mes || new Date().getMonth() + 1);

  useEffect(() => { api<{ itens: Item[] }>("budget", { ano, mes }).then(r => setItens(r.itens || [])).catch(() => setItens([])); }, [ano, mes]);
  const moeda = (v: number) => Number(v || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  const total = itens.reduce((s, i) => s + Number(i.valor_total || 0), 0);

  return <SafeAreaView style={styles.container}><ScrollView contentContainerStyle={styles.content}>
    <TouchableOpacity onPress={() => router.back()}><ArrowLeft size={24} color="#222" /></TouchableOpacity>
    <Text style={styles.title}>Budget Beauty Pet</Text><Text style={styles.subtitle}>Serviços registrados no WEB para a sua ficha e para os seus pets.</Text>
    <View style={styles.total}><Text style={styles.totalLabel}>Total do período</Text><Text style={styles.totalValue}>{moeda(total)}</Text></View>
    {itens.length === 0 ? <Text style={styles.empty}>Nenhum serviço encontrado neste mês.</Text> : itens.map((item, index) => <View key={`${item.data_fechamento}-${item.pet}-${item.servico}-${index}`} style={styles.card}><Text style={styles.pet}>{item.pet}</Text><Text style={styles.service}>{item.servico}</Text><Text style={styles.date}>{new Date(item.data_fechamento + "T12:00:00").toLocaleDateString("pt-BR")}</Text><Text style={styles.value}>{moeda(item.valor_total)}</Text></View>)}
  </ScrollView></SafeAreaView>;
}

const styles = StyleSheet.create({ container: { flex: 1, backgroundColor: "#FFF59D" }, content: { padding: 22, paddingTop: 45 }, title: { fontFamily: "Poppins_600SemiBold", fontSize: 22, marginTop: 20 }, subtitle: { fontFamily: "Poppins_400Regular", color: "#555", marginTop: 5, marginBottom: 18 }, total: { backgroundColor: "#5d57fb", borderRadius: 14, padding: 16, marginBottom: 16 }, totalLabel: { color: "#FFF", fontFamily: "Poppins_400Regular" }, totalValue: { color: "#FFF", fontFamily: "Poppins_600SemiBold", fontSize: 20 }, card: { backgroundColor: "#FFF", borderRadius: 12, padding: 14, marginBottom: 10 }, pet: { fontFamily: "Poppins_600SemiBold", fontSize: 16 }, service: { fontFamily: "Poppins_400Regular", marginTop: 2 }, date: { color: "#777", marginTop: 5 }, value: { fontFamily: "Poppins_600SemiBold", color: "#5d57fb", marginTop: 5 }, empty: { textAlign: "center", color: "#777", marginTop: 40 } });
