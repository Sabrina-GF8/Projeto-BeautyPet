import {
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

import {
  Poppins_400Regular,
  Poppins_600SemiBold,
  useFonts,
} from '@expo-google-fonts/poppins';

import {
  useFocusEffect,
  useRouter,
} from 'expo-router';

import {
  useCallback,
  useMemo,
  useState,
} from 'react';

import { api } from '../lib/api';

import {
  Calendar,
  LocaleConfig,
} from 'react-native-calendars';

import {
  CalendarDays,
  Clock,
  Home as HomeIcon,
  UserRound,
  Wallet,
} from 'lucide-react-native';


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


type EventoAgenda = {
  id: number;
  titulo: string;
  descricao?: string | null;
  data_hora_inicio: string;
  data_hora_fim?: string | null;
  status: string;
  pet_id?: number | null;
  pet?: string | null;
  categoria?: string | null;
};


function obterDataHoje() {

  const hoje = new Date();

  const ano =
    hoje.getFullYear();

  const mes = String(
    hoje.getMonth() + 1
  ).padStart(2, '0');

  const dia = String(
    hoje.getDate()
  ).padStart(2, '0');

  return `${ano}-${mes}-${dia}`;

}


export default function Agenda() {

  const router =
    useRouter();


  const [fontsLoaded] =
    useFonts({
      Poppins_400Regular,
      Poppins_600SemiBold,
    });


  const [
    diaSelecionado,
    setDiaSelecionado,
  ] = useState(
    obterDataHoje()
  );


  const [
    eventos,
    setEventos,
  ] = useState<EventoAgenda[]>([]);


  const [
    carregando,
    setCarregando,
  ] = useState(false);


  const [
    erroAgenda,
    setErroAgenda,
  ] = useState('');


  // ==========================================================
  // CARREGA A AGENDA TODA VEZ QUE A TELA GANHA FOCO
  // ==========================================================

  useFocusEffect(

    useCallback(() => {

      let telaAtiva = true;


      async function carregarEventos() {

        try {

          setCarregando(true);

          setErroAgenda('');


          const resposta =
            await api<{
              eventos: EventoAgenda[];
            }>(
              'agenda_pessoal'
            );


          if (!telaAtiva) {
            return;
          }


          const eventosRecebidos =
            Array.isArray(
              resposta.eventos
            )
              ? resposta.eventos
              : [];


          setEventos(
            eventosRecebidos
          );


          console.log(
            'EVENTOS DA AGENDA:',
            eventosRecebidos
          );


        } catch (erro) {

          console.log(
            'ERRO AO CARREGAR AGENDA:',
            erro
          );


          if (telaAtiva) {

            // Não fingimos mais que "não há eventos".
            // Agora mostramos que ocorreu erro de comunicação/sessão.
            setErroAgenda(
              erro instanceof Error
                ? erro.message
                : 'Não foi possível carregar os eventos.'
            );

          }

        } finally {

          if (telaAtiva) {

            setCarregando(false);

          }

        }

      }


      carregarEventos();


      return () => {

        telaAtiva = false;

      };

    }, [])

  );


  // ==========================================================
  // DATAS QUE POSSUEM EVENTOS
  // ==========================================================

  const datasComEvento =
    useMemo(() => {

      const datas =
        new Set<string>();


      eventos.forEach(
        (evento) => {

          const valor =
            String(
              evento.data_hora_inicio || ''
            );


          // Funciona tanto para:
          // 2026-09-19 09:00:00
          // quanto para:
          // 2026-09-19T09:00:00
          const dataEvento =
            valor.substring(0, 10);


          if (
            /^\d{4}-\d{2}-\d{2}$/.test(
              dataEvento
            )
          ) {

            datas.add(
              dataEvento
            );

          }

        }
      );


      return datas;

    }, [eventos]);


  // ==========================================================
  // EVENTOS APENAS DO DIA ESCOLHIDO
  // ==========================================================

  const eventosDoDia =
    useMemo(() => {

      return eventos

        .filter(
          (evento) => {

            const dataEvento =
              String(
                evento.data_hora_inicio || ''
              ).substring(0, 10);


            return (
              dataEvento ===
              diaSelecionado
            );

          }
        )

        .sort(
          (a, b) =>
            String(
              a.data_hora_inicio
            ).localeCompare(
              String(
                b.data_hora_inicio
              )
            )
        );

    }, [
      eventos,
      diaSelecionado,
    ]);


  function formatarHora(
    dataHora: string
  ) {

    if (!dataHora) {
      return '--:--';
    }


    const valor =
      String(dataHora);


    // MySQL geralmente devolve:
    // 2026-09-19 09:00:00
    //
    // Mas também aceitamos:
    // 2026-09-19T09:00:00
    const parteHora =
      valor.includes('T')
        ? valor.split('T')[1]
        : valor.split(' ')[1];


    return (
      parteHora?.substring(0, 5)
      || '--:--'
    );

  }


  function formatarTituloData(
    dataSql: string
  ) {

    const [
      ano,
      mes,
      dia,
    ] = dataSql
      .split('-')
      .map(Number);


    const data =
      new Date(
        ano,
        mes - 1,
        dia
      );


    let nomeMes =
      data.toLocaleDateString(
        'pt-BR',
        {
          month: 'long',
        }
      );


    nomeMes =
      nomeMes.charAt(0).toUpperCase()
      + nomeMes.slice(1);


    return (
      `Eventos de ${dia} de ${nomeMes}`
    );

  }


  if (!fontsLoaded) {
    return null;
  }


  return (

    <View style={styles.container}>


      <ScrollView
        style={styles.scroll}
        contentContainerStyle={
          styles.conteudo
        }
        showsVerticalScrollIndicator={
          false
        }
      >


        <View style={styles.cabecalho}>

          <View
            style={
              styles.textosCabecalho
            }
          >

            <Text style={styles.titulo}>
              Minha agenda
            </Text>

            <Text style={styles.subtitulo}>
              Gerencie os compromissos do seu pet
            </Text>

          </View>

        </View>


        <View
          style={
            styles.calendarioContainer
          }
        >

          <Calendar

            current={
              diaSelecionado
            }

            hideExtraDays={
              false
            }

            theme={{

              backgroundColor:
                '#FFFFFF',

              calendarBackground:
                '#FFFFFF',

              textSectionTitleColor:
                '#222222',

              dayTextColor:
                '#222222',

              monthTextColor:
                '#222222',

              textDisabledColor:
                '#BDBDBD',

              textMonthFontFamily:
                'Poppins_600SemiBold',

              textMonthFontSize:
                16,

              textDayHeaderFontFamily:
                'Poppins_400Regular',

              textDayHeaderFontSize:
                10,

              textDayFontFamily:
                'Poppins_400Regular',

              textDayFontSize:
                14,

              arrowColor:
                '#222222',

              todayTextColor:
                '#222222',

            }}


            dayComponent={({
              date,
              state,
            }) => {

              const isDisabled =
                state === 'disabled';


              const dataDoDia =
                date?.dateString || '';


              const temEvento =
                datasComEvento.has(
                  dataDoDia
                );


              const selecionado =
                dataDoDia ===
                diaSelecionado;


              return (

                <TouchableOpacity

                  // Dias de outro mês ficam visualmente desativados.
                  // Porém o calendário continua funcionando normalmente.
                  disabled={
                    isDisabled
                  }

                  activeOpacity={
                    0.7
                  }

                  onPress={() => {

                    if (
                      dataDoDia
                    ) {

                      setDiaSelecionado(
                        dataDoDia
                      );

                    }

                  }}

                  style={[

                    styles.dia,

                    !isDisabled &&
                      styles.diaDisponivel,

                    selecionado &&
                      styles.diaSelecionado,

                  ]}
                >


                  <Text
                    style={[

                      styles.numeroDia,

                      isDisabled &&
                        styles.numeroDiaDesativado,

                    ]}
                  >

                    {date?.day}

                  </Text>


                  {temEvento && (

                    <View
                      style={
                        styles.pontoEvento
                      }
                    />

                  )}


                </TouchableOpacity>

              );

            }}

          />

        </View>


        {/* ===================================================
            EVENTOS DO DIA
        ==================================================== */}

        <View
          style={
            styles.eventosArea
          }
        >

          <Text
            style={
              styles.eventosTitulo
            }
          >

            {formatarTituloData(
              diaSelecionado
            )}

          </Text>


          {carregando ? (

            <Text
              style={
                styles.semEvento
              }
            >
              Carregando eventos...
            </Text>

          ) : erroAgenda ? (

            <Text
              style={
                styles.erroEvento
              }
            >
              {erroAgenda}
            </Text>

          ) : eventosDoDia.length === 0 ? (

            <Text
              style={
                styles.semEvento
              }
            >
              Nenhum evento marcado
            </Text>

          ) : (

            eventosDoDia.map(
              (evento) => (

                <View
                  key={
                    evento.id
                  }
                  style={
                    styles.eventoCard
                  }
                >

                  <View
                    style={
                      styles.eventoInfo
                    }
                  >

                    <Text
                      style={
                        styles.eventoNome
                      }
                    >
                      {evento.titulo}
                    </Text>


                    <Text
                      style={
                        styles.eventoPet
                      }
                    >

                      {evento.pet

                        ? `🐾 ${evento.pet}`

                        : evento.categoria

                          ? evento.categoria

                          : 'Evento pessoal'
                      }

                    </Text>


                    {!!evento.descricao && (

                      <Text
                        style={
                          styles.eventoDescricao
                        }
                        numberOfLines={
                          1
                        }
                      >
                        {evento.descricao}
                      </Text>

                    )}

                  </View>


                  <View
                    style={
                      styles.horaBadge
                    }
                  >

                    <Clock
                      size={14}
                      color="#222222"
                    />

                    <Text
                      style={
                        styles.horaTexto
                      }
                    >
                      {formatarHora(
                        evento.data_hora_inicio
                      )}
                    </Text>

                  </View>

                </View>

              )
            )

          )}

        </View>


        <TouchableOpacity
          style={
            styles.botaoMais
          }
          activeOpacity={
            0.6
          }
          onPress={() => {

            router.push(
              '/novo_evento'
            );

          }}
        >

          <Text
            style={
              styles.mais
            }
          >
            +
          </Text>

        </TouchableOpacity>


      </ScrollView>


      {/* MENU INFERIOR */}

      <View
        style={
          styles.bottomMenu
        }
      >


        <TouchableOpacity
          style={
            styles.menuItem
          }
          onPress={() =>
            router.push(
              '/home'
            )
          }
        >

          <HomeIcon
            size={25}
            color="#5F6269"
          />

          <Text
            style={
              styles.menuText
            }
          >
            Home
          </Text>

        </TouchableOpacity>


        <TouchableOpacity
          style={
            styles.menuItem
          }
          onPress={() =>
            router.push(
              '/agenda'
            )
          }
        >

          <CalendarDays
            size={25}
            color="#EF3038"
          />

          <Text
            style={
              styles.menuTextActive
            }
          >
            Agenda
          </Text>

          <View
            style={
              styles.activeLine
            }
          />

        </TouchableOpacity>


        <TouchableOpacity
          style={
            styles.menuItem
          }
          onPress={() =>
            router.push(
              '/financeiro'
            )
          }
        >

          <Wallet
            size={25}
            color="#5F6269"
          />

          <Text
            style={
              styles.menuText
            }
          >
            Financeiro
          </Text>

        </TouchableOpacity>


        <TouchableOpacity
          style={
            styles.menuItem
          }
          onPress={() =>
            router.push(
              '/perfil'
            )
          }
        >

          <UserRound
            size={25}
            color="#5F6269"
          />

          <Text
            style={
              styles.menuText
            }
          >
            Perfil
          </Text>

        </TouchableOpacity>


      </View>


    </View>

  );

}


const styles =
  StyleSheet.create({

    container: {
      flex: 1,
      backgroundColor: '#FFF4AB',
    },

    scroll: {
      flex: 1,
    },

    conteudo: {
      paddingTop: 60,
      paddingHorizontal: 20,
      paddingBottom: 100,
    },

    cabecalho: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'flex-start',
    },

    textosCabecalho: {
      marginTop: 15,
    },

    titulo: {
      fontFamily: 'Poppins_600SemiBold',
      fontSize: 20,
      color: '#222222',
    },

    subtitulo: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 14,
      color: '#111111',
      marginTop: 3,
    },

    calendarioContainer: {
      marginTop: 50,
      backgroundColor: '#FFFFFF',
      borderRadius: 15,
      overflow: 'hidden',

      shadowColor: '#000000',
      shadowOffset: {
        width: 0,
        height: 3,
      },
      shadowOpacity: 0.18,
      shadowRadius: 5,

      elevation: 5,
      paddingBottom: 8,
    },

    dia: {
      width: 32,
      height: 36,
      borderRadius: 6,
      justifyContent: 'center',
      alignItems: 'center',
    },

    diaDisponivel: {
      backgroundColor: '#FFF59D',
    },

    diaSelecionado: {
      backgroundColor: '#FFE16B',
      borderWidth: 2,
      borderColor: '#EF3038',
    },

    numeroDia: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 14,
      color: '#222222',
    },

    numeroDiaDesativado: {
      color: '#BDBDBD',
    },

    pontoEvento: {
      width: 5,
      height: 5,
      borderRadius: 3,
      backgroundColor: '#EF3038',
      marginTop: 1,
    },

    eventosArea: {
      marginTop: 28,
    },

    eventosTitulo: {
      fontFamily: 'Poppins_600SemiBold',
      fontSize: 15,
      color: '#222222',
      marginBottom: 12,
    },

    semEvento: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 13,
      color: '#777777',
      textAlign: 'center',
      marginTop: 16,
      marginBottom: 15,
    },

    erroEvento: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 12,
      color: '#C62828',
      textAlign: 'center',
      marginTop: 16,
      marginBottom: 15,
      paddingHorizontal: 10,
    },

    eventoCard: {
      minHeight: 72,
      backgroundColor: '#FFF',
      borderRadius: 10,
      paddingHorizontal: 14,
      paddingVertical: 11,
      marginBottom: 10,

      flexDirection: 'row',
      alignItems: 'center',
      justifyContent: 'space-between',

      shadowColor: '#000000',
      shadowOffset: {
        width: 0,
        height: 2,
      },
      shadowOpacity: 0.12,
      shadowRadius: 4,

      elevation: 3,
    },

    eventoInfo: {
      flex: 1,
      paddingRight: 10,
    },

    eventoNome: {
      fontFamily: 'Poppins_600SemiBold',
      fontSize: 14,
      color: '#222222',
    },

    eventoPet: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 11,
      color: '#555555',
      marginTop: 2,
    },

    eventoDescricao: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 10,
      color: '#777777',
      marginTop: 2,
    },

    horaBadge: {
      flexDirection: 'row',
      alignItems: 'center',

      backgroundColor: '#FFF4AB',
      borderRadius: 14,

      paddingHorizontal: 10,
      paddingVertical: 5,

      gap: 5,
    },

    horaTexto: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 11,
      color: '#222222',
    },

    botaoMais: {
      position: 'absolute',
      top: 74,
      right: 20,

      width: 48,
      height: 48,
      borderRadius: 24,

      backgroundColor: '#FFFFFF',

      justifyContent: 'center',
      alignItems: 'center',

      shadowColor: '#000000',
      shadowOffset: {
        width: 0,
        height: 2,
      },
      shadowOpacity: 0.16,
      shadowRadius: 3,

      elevation: 5,
      zIndex: 999,
    },

    mais: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 34,
      lineHeight: 38,
      color: '#222222',
      marginTop: -2,
    },

    bottomMenu: {
      position: 'absolute',
      bottom: 0,
      left: 0,
      right: 0,

      height: 75,

      backgroundColor: '#FFFFFF',

      borderTopWidth: 1,
      borderTopColor: '#E5E5E5',

      flexDirection: 'row',
      justifyContent: 'space-around',
      alignItems: 'center',
    },

    menuItem: {
      flex: 1,
      alignItems: 'center',
      justifyContent: 'center',
      height: '100%',
    },

    menuText: {
      fontFamily: 'Poppins_400Regular',
      fontSize: 11,
      color: '#5F6269',
      marginTop: 3,
    },

    menuTextActive: {
      fontFamily: 'Poppins_600SemiBold',
      fontSize: 11,
      color: '#EF3038',
      marginTop: 3,
    },

    activeLine: {
      position: 'absolute',
      bottom: 0,
      width: 35,
      height: 3,
      backgroundColor: '#EF3038',
      borderRadius: 3,
    },

  });
