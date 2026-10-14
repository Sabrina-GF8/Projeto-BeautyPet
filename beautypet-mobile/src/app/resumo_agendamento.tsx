import {
    ActivityIndicator,
    Alert,
    SafeAreaView,
    ScrollView,
    StyleSheet,
    Text,
    TouchableOpacity,
    View,
} from 'react-native';

import {
    useLocalSearchParams,
    useRouter,
} from 'expo-router';

import {
    ArrowLeft,
    CalendarDays,
    Clock3,
    PawPrint,
    Scissors,
} from 'lucide-react-native';

import {
    useEffect,
    useMemo,
    useState,
} from 'react';

import { api } from '../lib/api';

type Pet = {
  id: number;
  nome: string;
};

type Servico = {
  id: number;
  nome: string;
};

const NOMES_MESES = [
  'janeiro',
  'fevereiro',
  'março',
  'abril',
  'maio',
  'junho',
  'julho',
  'agosto',
  'setembro',
  'outubro',
  'novembro',
  'dezembro',
];

function formatarData(data: string) {
  const [ano, mes, dia] =
    data.split('-').map(Number);

  if (!ano || !mes || !dia) {
    return data;
  }

  return `${String(dia).padStart(
    2,
    '0'
  )} de ${NOMES_MESES[mes - 1]} de ${ano}`;
}

export default function ResumoAgendamento() {
  const router = useRouter();

  const params = useLocalSearchParams<{
    petId?: string;
    servicos?: string;
    data?: string;
    horario?: string;
  }>();

  const [petNome, setPetNome] =
    useState('');

  const [servicosNomes, setServicosNomes] =
    useState<string[]>([]);

  const [
    carregandoResumo,
    setCarregandoResumo,
  ] = useState(true);

  const [enviando, setEnviando] =
    useState(false);

  const petId = Number(params.petId);
  const data = String(params.data || '');
  const horario = String(params.horario || '');

  const servicoIds = useMemo(
    () =>
      String(params.servicos || '')
        .split(',')
        .map((item) => Number(item))
        .filter(
          (id) => Number.isInteger(id) && id > 0
        ),
    [params.servicos]
  );

  useEffect(() => {
    let telaAtiva = true;

    async function carregarResumo() {
      try {
        const [
          respostaPets,
          respostaServicos,
        ] = await Promise.all([
          api<{ pets?: Pet[] }>('meus_pets'),

          api<{ servicos?: Servico[] }>(
            'servicos'
          ),
        ]);

        if (!telaAtiva) {
          return;
        }

        const petSelecionado =
          (respostaPets.pets || []).find(
            (pet) =>
              Number(pet.id) === petId
          );

        const catalogoServicos =
          respostaServicos.servicos || [];

        const nomesSelecionados =
          servicoIds.map((id) => {
            return (
              catalogoServicos.find(
                (servico) =>
                  Number(servico.id) === id
              )?.nome || `Serviço ${id}`
            );
          });

        setPetNome(
          petSelecionado?.nome ||
            `Pet ${petId}`
        );

        setServicosNomes(
          nomesSelecionados
        );
      } catch {
        if (telaAtiva) {
          setPetNome(
            petId
              ? `Pet ${petId}`
              : 'Pet não identificado'
          );

          setServicosNomes(
            servicoIds.map(
              (id) => `Serviço ${id}`
            )
          );
        }
      } finally {
        if (telaAtiva) {
          setCarregandoResumo(false);
        }
      }
    }

    void carregarResumo();

    return () => {
      telaAtiva = false;
    };
  }, [petId, servicoIds]);

  async function confirmarAgendamento() {
    if (
      !petId ||
      servicoIds.length === 0 ||
      !data ||
      !horario
    ) {
      Alert.alert(
        'Agendamento',
        'Os dados do agendamento estão incompletos. Volte e tente novamente.'
      );

      return;
    }

    if (enviando) {
      return;
    }

    try {
      setEnviando(true);

      const resposta =
        await api<{ mensagem?: string }>(
          'solicitar_agendamento',
          {
            pet_id: petId,
            servico_ids: servicoIds,
            data,
            horario,
            duracao_minutos: 60,
          }
        );

      Alert.alert(
        'Solicitação enviada!',
        resposta.mensagem ||
          'O Beauty Pet recebeu a sua solicitação de agendamento.',
        [
          {
            text: 'OK',

            onPress: () =>
              router.replace('/home'),
          },
        ]
      );
    } catch (erro) {
      Alert.alert(
        'Agendamento',

        erro instanceof Error
          ? erro.message
          : 'Não foi possível enviar o agendamento.'
      );
    } finally {
      setEnviando(false);
    }
  }

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.conteudo}
        showsVerticalScrollIndicator={false}
      >
        <TouchableOpacity
          style={styles.botaoVoltar}
          onPress={() => router.back()}
        >
          <ArrowLeft
            size={24}
            color="#222222"
          />
        </TouchableOpacity>

        <Text style={styles.titulo}>
          Quase lá!
        </Text>

        <Text style={styles.subtitulo}>
          Confira os dados antes de enviar
          sua solicitação.
        </Text>

        <View style={styles.petCirculo}>
          <PawPrint
            size={38}
            color="#EF3038"
          />
        </View>

        <View style={styles.resumoCard}>
          <Text style={styles.tituloCard}>
            Resumo do agendamento
          </Text>

          {carregandoResumo ? (
            <ActivityIndicator
              color="#EF3038"
              style={styles.carregando}
            />
          ) : (
            <>
              <View style={styles.itemResumo}>
                <View
                  style={[
                    styles.iconeResumo,
                    styles.fundoPet,
                  ]}
                >
                  <PawPrint
                    size={20}
                    color="#333333"
                  />
                </View>

                <View style={styles.textosItem}>
                  <Text style={styles.rotuloItem}>
                    Pet
                  </Text>

                  <Text style={styles.valorItem}>
                    {petNome}
                  </Text>
                </View>
              </View>

              {servicosNomes.map(
                (servico, index) => (
                  <View
                    key={`${servico}-${index}`}
                    style={styles.itemResumo}
                  >
                    <View
                      style={[
                        styles.iconeResumo,
                        styles.fundoServico,
                      ]}
                    >
                      <Scissors
                        size={20}
                        color="#333333"
                      />
                    </View>

                    <View
                      style={styles.textosItem}
                    >
                      <Text
                        style={styles.rotuloItem}
                      >
                        Serviço
                      </Text>

                      <Text
                        style={styles.valorItem}
                      >
                        {servico}
                      </Text>
                    </View>
                  </View>
                )
              )}

              <View style={styles.itemResumo}>
                <View
                  style={[
                    styles.iconeResumo,
                    styles.fundoData,
                  ]}
                >
                  <CalendarDays
                    size={20}
                    color="#333333"
                  />
                </View>

                <View style={styles.textosItem}>
                  <Text style={styles.rotuloItem}>
                    Data
                  </Text>

                  <Text style={styles.valorItem}>
                    {formatarData(data)}
                  </Text>
                </View>
              </View>

              <View style={styles.itemResumo}>
                <View
                  style={[
                    styles.iconeResumo,
                    styles.fundoHorario,
                  ]}
                >
                  <Clock3
                    size={20}
                    color="#333333"
                  />
                </View>

                <View style={styles.textosItem}>
                  <Text style={styles.rotuloItem}>
                    Horário
                  </Text>

                  <Text style={styles.valorItem}>
                    {horario}
                  </Text>
                </View>
              </View>
            </>
          )}
        </View>

        <View style={styles.avisoCard}>
          <Text style={styles.avisoTitulo}>
            Aguarde!
          </Text>

          <Text style={styles.avisoTexto}>
            Depois de confirmar, o Beauty Pet
            analisará a solicitação e você receberá
            uma confirmação em breve.
          </Text>
        </View>

        <TouchableOpacity
          style={[
            styles.confirmButton,

            (carregandoResumo || enviando) &&
              styles.botaoDesativado,
          ]}
          activeOpacity={0.85}
          disabled={
            carregandoResumo || enviando
          }
          onPress={confirmarAgendamento}
        >
          {enviando ? (
            <ActivityIndicator color="#222222" />
          ) : (
            <Text style={styles.confirmText}>
              Confirmar
            </Text>
          )}
        </TouchableOpacity>
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#FFF4AB',
  },

  conteudo: {
    flexGrow: 1,
    paddingHorizontal: 22,
    paddingTop: 24,
    paddingBottom: 28,
  },

  botaoVoltar: {
    width: 40,
    height: 40,
    justifyContent: 'center',
  },

  titulo: {
    marginTop: 4,
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 21,
    color: '#222222',
  },

  subtitulo: {
    marginTop: 3,
    maxWidth: 310,
    fontFamily: 'Poppins_400Regular',
    fontSize: 12,
    lineHeight: 18,
    color: '#444444',
  },

  petCirculo: {
    alignSelf: 'center',
    width: 76,
    height: 76,
    borderRadius: 38,
    marginTop: 18,
    marginBottom: -25,
    backgroundColor: '#FFFFFF',
    borderWidth: 3,
    borderColor: '#EF3038',
    justifyContent: 'center',
    alignItems: 'center',
    zIndex: 2,

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 2,
    },

    shadowOpacity: 0.14,
    shadowRadius: 4,
    elevation: 4,
  },

  resumoCard: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    paddingHorizontal: 15,
    paddingTop: 34,
    paddingBottom: 10,

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 3,
    },

    shadowOpacity: 0.16,
    shadowRadius: 5,
    elevation: 5,
  },

  tituloCard: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 14,
    color: '#222222',
    textAlign: 'center',
    marginBottom: 12,
  },

  carregando: {
    paddingVertical: 42,
  },

  itemResumo: {
    minHeight: 58,
    backgroundColor: '#F8F8F8',
    borderRadius: 12,
    paddingHorizontal: 10,
    marginBottom: 9,
    flexDirection: 'row',
    alignItems: 'center',
  },

  iconeResumo: {
    width: 38,
    height: 38,
    borderRadius: 10,
    justifyContent: 'center',
    alignItems: 'center',
  },

  fundoPet: {
    backgroundColor: '#DDF3FF',
  },

  fundoServico: {
    backgroundColor: '#FFE0E0',
  },

  fundoData: {
    backgroundColor: '#F0D7FF',
  },

  fundoHorario: {
    backgroundColor: '#DED7FF',
  },

  textosItem: {
    flex: 1,
    marginLeft: 10,
  },

  rotuloItem: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 9,
    color: '#666666',
  },

  valorItem: {
    marginTop: 1,
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 12,
    color: '#222222',
  },

  avisoCard: {
    marginTop: 18,
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    padding: 14,
  },

  avisoTitulo: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 12,
    color: '#222222',
  },

  avisoTexto: {
    marginTop: 3,
    fontFamily: 'Poppins_400Regular',
    fontSize: 10,
    lineHeight: 16,
    color: '#555555',
  },

  confirmButton: {
    height: 50,
    marginTop: 20,
    backgroundColor: '#FFE16B',
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: '#D4B52F',

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 2,
    },

    shadowOpacity: 0.14,
    shadowRadius: 3,
    elevation: 3,
  },

  botaoDesativado: {
    opacity: 0.6,
  },

  confirmText: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 14,
    color: '#222222',
  },
});