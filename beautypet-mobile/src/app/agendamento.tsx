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

import { useLocalSearchParams, useRouter } from 'expo-router';
import { ArrowLeft, Clock3 } from 'lucide-react-native';
import { useEffect, useMemo, useState } from 'react';
import { Calendar, LocaleConfig } from 'react-native-calendars';
import { api } from '../lib/api';

LocaleConfig.locales['pt-br'] = {
  monthNames: [
    'Janeiro',
    'Fevereiro',
    'Março',
    'Abril',
    'Maio',
    'Junho',
    'Julho',
    'Agosto',
    'Setembro',
    'Outubro',
    'Novembro',
    'Dezembro',
  ],

  monthNamesShort: [
    'Jan.',
    'Fev.',
    'Mar.',
    'Abr.',
    'Mai.',
    'Jun.',
    'Jul.',
    'Ago.',
    'Set.',
    'Out.',
    'Nov.',
    'Dez.',
  ],

  dayNames: [
    'Domingo',
    'Segunda-feira',
    'Terça-feira',
    'Quarta-feira',
    'Quinta-feira',
    'Sexta-feira',
    'Sábado',
  ],

  dayNamesShort: [
    'Dom',
    'Seg',
    'Ter',
    'Qua',
    'Qui',
    'Sex',
    'Sáb',
  ],
};

LocaleConfig.defaultLocale = 'pt-br';

function doisDigitos(valor: number) {
  return String(valor).padStart(2, '0');
}

function hojeLocal() {
  const hoje = new Date();

  return `${hoje.getFullYear()}-${doisDigitos(
    hoje.getMonth() + 1
  )}-${doisDigitos(hoje.getDate())}`;
}

export default function Agendamento() {
  const router = useRouter();

  const params = useLocalSearchParams<{
    petId?: string;
    servicos?: string;
  }>();

  const [diaSelecionado, setDiaSelecionado] =
    useState('');

  const [horarioSelecionado, setHorarioSelecionado] =
    useState('');

  const [horarios, setHorarios] =
    useState<string[]>([]);

  const [carregandoHorarios, setCarregandoHorarios] =
    useState(false);

  const petId = Number(params.petId);

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
    setHorarioSelecionado('');

    if (
      !diaSelecionado ||
      servicoIds.length === 0
    ) {
      setHorarios([]);
      return;
    }

    setCarregandoHorarios(true);

    api<{ horarios?: string[] }>(
      'horarios_disponiveis',
      {
        data: diaSelecionado,
        servico_ids: servicoIds,
        duracao_minutos: 60,
      }
    )
      .then((resposta) => {
        setHorarios(resposta.horarios || []);
      })
      .catch((erro) => {
        setHorarios([]);

        Alert.alert(
          'Agendamento',
          erro instanceof Error
            ? erro.message
            : 'Não foi possível consultar os horários.'
        );
      })
      .finally(() => {
        setCarregandoHorarios(false);
      });
  }, [diaSelecionado, servicoIds]);

  function abrirResumo() {
    if (
      !petId ||
      servicoIds.length === 0
    ) {
      Alert.alert(
        'Atenção',
        'Volte e selecione o pet e os serviços novamente.'
      );

      return;
    }

    if (!diaSelecionado) {
      Alert.alert(
        'Atenção',
        'Escolha uma data.'
      );

      return;
    }

    if (!horarioSelecionado) {
      Alert.alert(
        'Atenção',
        'Escolha um horário.'
      );

      return;
    }

    router.push({
      pathname: '/resumo_agendamento',

      params: {
        petId: String(petId),
        servicos: servicoIds.join(','),
        data: diaSelecionado,
        horario: horarioSelecionado,
      },
    });
  }

  const dataMarcada = diaSelecionado
    ? {
        [diaSelecionado]: {
          selected: true,
          selectedColor: '#FFF59D',
          selectedTextColor: '#222222',
        },
      }
    : {};

  return (
    <SafeAreaView style={styles.container}>
      <ScrollView
        contentContainerStyle={styles.conteudo}
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.header}>
          <TouchableOpacity
            onPress={() => router.back()}
            style={styles.botaoVoltar}
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

        <View style={styles.calendarContainer}>
          <Calendar
            current={hojeLocal()}
            minDate={hojeLocal()}
            hideExtraDays={false}
            enableSwipeMonths
            markedDates={dataMarcada}
            onDayPress={(dia) =>
              setDiaSelecionado(dia.dateString)
            }
            theme={{
              backgroundColor: '#FFFFFF',
              calendarBackground: '#FFFFFF',

              textSectionTitleColor: '#222222',
              dayTextColor: '#222222',
              monthTextColor: '#222222',
              textDisabledColor: '#BDBDBD',

              textMonthFontFamily:
                'Poppins_600SemiBold',

              textMonthFontSize: 16,

              textDayHeaderFontFamily:
                'Poppins_400Regular',

              textDayHeaderFontSize: 10,

              textDayFontFamily:
                'Poppins_400Regular',

              textDayFontSize: 14,

              arrowColor: '#222222',
              todayTextColor: '#EF3038',

              selectedDayBackgroundColor:
                '#FFF59D',

              selectedDayTextColor:
                '#222222',
            }}
          />
        </View>

        <View style={styles.scheduleArea}>
          <View style={styles.scheduleTitle}>
            <Clock3
              size={19}
              color="#333333"
            />

            <Text style={styles.scheduleText}>
              {carregandoHorarios
                ? 'Carregando horários...'
                : 'Horários disponíveis'}
            </Text>
          </View>

          {carregandoHorarios ? (
            <ActivityIndicator
              color="#EF3038"
              style={styles.carregando}
            />
          ) : (
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
                    activeOpacity={0.8}
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

              {diaSelecionado &&
                horarios.length === 0 && (
                  <Text style={styles.emptyText}>
                    Nenhum horário disponível
                    para esta data.
                  </Text>
                )}

              {!diaSelecionado && (
                <Text style={styles.emptyText}>
                  Escolha uma data para consultar
                  os horários.
                </Text>
              )}
            </View>
          )}
        </View>

        <TouchableOpacity
          style={[
            styles.confirmButton,

            (!diaSelecionado ||
              !horarioSelecionado) &&
              styles.confirmButtonDisabled,
          ]}
          activeOpacity={0.85}
          onPress={abrirResumo}
        >
          <Text style={styles.confirmText}>
            Confirmar
          </Text>
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
    paddingHorizontal: 20,
    paddingTop: 28,
    paddingBottom: 28,
  },

  header: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },

  botaoVoltar: {
    width: 38,
    height: 38,
    justifyContent: 'center',
    alignItems: 'flex-start',
  },

  headerText: {
    marginLeft: 6,
    paddingTop: 4,
  },

  title: {
    fontSize: 20,
    color: '#222222',
    fontFamily: 'Poppins_600SemiBold',
  },

  subtitle: {
    fontSize: 13,
    color: '#555555',
    marginTop: 3,
    fontFamily: 'Poppins_400Regular',
  },

  calendarContainer: {
    marginTop: 30,
    backgroundColor: '#FFFFFF',
    borderRadius: 15,
    overflow: 'hidden',
    paddingBottom: 8,

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 3,
    },

    shadowOpacity: 0.18,
    shadowRadius: 5,
    elevation: 5,
  },

  scheduleArea: {
    marginTop: 24,
    backgroundColor: '#FFFFFF',
    borderRadius: 15,
    padding: 16,

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 2,
    },

    shadowOpacity: 0.1,
    shadowRadius: 4,
    elevation: 3,
  },

  scheduleTitle: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 14,
  },

  scheduleText: {
    fontSize: 14,
    color: '#333333',
    marginLeft: 8,
    fontFamily: 'Poppins_600SemiBold',
  },

  carregando: {
    paddingVertical: 18,
  },

  timesContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 9,
  },

  timeButton: {
    minWidth: '30%',
    height: 40,
    paddingHorizontal: 12,
    backgroundColor: '#F1F1F1',
    borderRadius: 20,
    borderWidth: 1,
    borderColor: '#E2E2E2',
    justifyContent: 'center',
    alignItems: 'center',
  },

  timeSelected: {
    backgroundColor: '#FFF59D',
    borderColor: '#E7C83E',
  },

  timeText: {
    fontSize: 12,
    color: '#333333',
    fontFamily: 'Poppins_400Regular',
  },

  timeTextSelected: {
    fontFamily: 'Poppins_600SemiBold',
  },

  emptyText: {
    width: '100%',
    fontSize: 12,
    lineHeight: 18,
    color: '#777777',
    fontFamily: 'Poppins_400Regular',
  },

  confirmButton: {
    height: 50,
    backgroundColor: '#FFE16B',
    borderRadius: 12,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 28,

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 2,
    },

    shadowOpacity: 0.14,
    shadowRadius: 3,
    elevation: 3,
  },

  confirmButtonDisabled: {
    opacity: 0.55,
  },

  confirmText: {
    fontSize: 14,
    color: '#222222',
    fontFamily: 'Poppins_600SemiBold',
  },
});