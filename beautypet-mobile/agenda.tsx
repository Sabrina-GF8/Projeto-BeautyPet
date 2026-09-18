import { ScrollView, StyleSheet, Text, TouchableOpacity, View } from 'react-native';

import { Poppins_400Regular, Poppins_600SemiBold, useFonts, } from '@expo-google-fonts/poppins';

import { useRouter } from 'expo-router';

import { Calendar, LocaleConfig } from 'react-native-calendars';


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

export default function Agenda() {

  const router = useRouter();

  const [fontsLoaded] = 
  useFonts({Poppins_400Regular, Poppins_600SemiBold,});

  if (!fontsLoaded) {
    return null;
  }

  return (
    <View style={styles.container}>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.conteudo}
        showsVerticalScrollIndicator={false}>

        <View style={styles.cabecalho}>

          <View style={styles.textosCabecalho}>
            <Text style={styles.titulo}>
              Minha agenda
            </Text>

            <Text style={styles.subtitulo}>
              Gerencie os compromissos do seu pet
            </Text>
          </View>

        </View>

        <View style={styles.calendarioContainer}>

          <Calendar
            current={'2026-09-01'}
            hideExtraDays={false}
            theme={{
              backgroundColor: '#FFFFFF',
              calendarBackground: '#FFFFFF',
              textSectionTitleColor: '#222222',
              dayTextColor: '#222222',
              monthTextColor: '#222222',
              textDisabledColor: '#BDBDBD',
              textMonthFontFamily: 'Poppins_600SemiBold',
              textMonthFontSize: 16,
              textDayHeaderFontFamily: 'Poppins_400Regular',
              textDayHeaderFontSize: 10,
              textDayFontFamily: 'Poppins_400Regular',
              textDayFontSize: 14,
              arrowColor: '#222222',
              todayTextColor: '#222222',
            }}

            dayComponent={({ date, state }) => {
              const isDisabled = state === 'disabled';
              return (
                <View
                  style={[
                    styles.dia,
                    !isDisabled && styles.diaDisponivel,
                  ]}>

                  <Text
                    style={[
                      styles.numeroDia,
                      isDisabled &&
                        styles.numeroDiaDesativado,
                    ]}
                  >
                    {date?.day}
                  </Text>

                </View>
              );
            }}
          />

        </View>

        <TouchableOpacity
          style={styles.botaoMais}
          activeOpacity={0.6}
          onPress={() => {
            console.log('CLIQUEI NO BOTÃO +');
            router.push('/novo_evento');
          }}>

          <Text style={styles.mais}>
            +
          </Text>

        </TouchableOpacity>

      </ScrollView>

    </View>
  );
}

const styles = StyleSheet.create({

  container: {
    flex: 1,
    backgroundColor: '#FFF59D',
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

  numeroDia: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 14,
    color: '#222222',
  },

  numeroDiaDesativado: {
    color: '#BDBDBD',
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
  shadowOffset: { width: 0, height: 2 },
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

});