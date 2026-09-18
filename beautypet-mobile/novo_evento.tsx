import { Image, Pressable, ScrollView, StyleSheet, Text, TextInput, TouchableOpacity, View, } from 'react-native';

import { useState } from 'react';

import { Poppins_400Regular, Poppins_600SemiBold, useFonts, } from '@expo-google-fonts/poppins';

import { useRouter } from 'expo-router';

import DateTimePicker from '@react-native-community/datetimepicker';

export default function NovoEvento() {
  const router = useRouter();

  const [data, setData] = useState(new Date());
  const [mostrarCalendario, setMostrarCalendario] = useState(false);

  const [horario, setHorario] = useState(new Date());
  const [mostrarHorario, setMostrarHorario] = useState(false);

  const [fontsLoaded] = useFonts({Poppins_400Regular, Poppins_600SemiBold,});

  if (!fontsLoaded) {
    return null;
  }

  return (
    <ScrollView
      style={styles.container}
      contentContainerStyle={styles.conteudo}
      showsVerticalScrollIndicator={false}>

      <View style={styles.cabecalho}>

        <View>
          <Text style={styles.titulo}>
            Minha agenda
          </Text>

          <Text style={styles.subtitulo}>
            Gerencie os compromissos do seu pet
          </Text>
        </View>

       <Image
  source={require('../assets/images/BeautyPet.jpg')}
  style={styles.iconePet}/>

      </View>

      <View style={styles.card}>

        <Text style={styles.tituloCard}>
          Novo evento
        </Text>

        <View style={styles.campoContainer}>

          <Text style={styles.label}>
            Evento
          </Text>

          <TextInput
            style={styles.input}
            placeholder="Ex: Banho e tosa"
            placeholderTextColor="#777777"
          />

        </View>

     <View style={styles.campoContainer}>
  <Text style={styles.label}>
    Horário
  </Text>

  <TouchableOpacity
    style={styles.input}
    activeOpacity={0.7}
    onPress={() => setMostrarHorario(true)}>
  
    <Text style={styles.textoHorario}>
      {horario.toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
      })}
    </Text>
  </TouchableOpacity>

  {mostrarHorario && (
    <DateTimePicker
      value={horario}
      mode="time"
      display="default"
      onChange={(event, selectedTime) => {
        setMostrarHorario(false);

        if (selectedTime) {
          setHorario(selectedTime);
        }
      }}
    />
  )}
</View>

        <View style={styles.campoContainer}>
  <Text style={styles.label}>
    Data
  </Text>

  <TouchableOpacity
    style={styles.input}
    activeOpacity={0.7}
    onPress={() => setMostrarCalendario(true)}
  >
    <Text style={styles.textoData}>
      {data.toLocaleDateString('pt-BR')}
    </Text>
  </TouchableOpacity>

  {mostrarCalendario && (
    <DateTimePicker
      value={data}
      mode="date"
      display="default"
      onChange={(event, selectedDate) => {
        setMostrarCalendario(false);

        if (selectedDate) {
          setData(selectedDate);
        }
      }}
    />
  )}
</View>

        <View style={styles.campoContainer}>

          <Text style={styles.label}>
            Detalhes
          </Text>

          <TextInput
            style={[styles.input, styles.inputDetalhes]}
            placeholder="Adicione detalhes sobre o evento..."
            placeholderTextColor="#777777"
            multiline
            textAlignVertical="top"
          />

        </View>

      </View>

      <View style={styles.botoes}>

        <Pressable
          style={styles.botao}
          onPress={() => router.back()}
        >
          <Text style={styles.textoBotao}>
            Cancelar
          </Text>
        </Pressable>

        <Pressable
          style={styles.botao}
          onPress={() => {
            console.log('Criar evento');
          }}
        >
          <Text style={styles.textoBotao}>
            Criar evento
          </Text>
        </Pressable>

      </View>

    </ScrollView>
  );
}

const styles = StyleSheet.create({

  container: {
    flex: 1,
    backgroundColor: '#FFF59D',
  },

  conteudo: {
    paddingTop: 55,
    paddingHorizontal: 20,
    paddingBottom: 40,
  },

  cabecalho: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
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

  iconePet: {
  width: 55,
  height: 55,
  borderRadius: 27.5,
  resizeMode: 'cover',
  marginTop: -5,
},

  card: {
    marginTop: 35,
    backgroundColor: '#FFFFFF',
    borderRadius: 14,
    paddingHorizontal: 19,
    paddingTop: 14,
    paddingBottom: 28,
    shadowColor: '#000000',
    shadowOffset: {
      width: 0,
      height: 3,
    },
    shadowOpacity: 0.22,
    shadowRadius: 4,
    elevation: 5,
  },

  tituloCard: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 19,
    color: '#222222',
    marginBottom: 21,
  },

  campoContainer: {
    marginBottom: 20,
  },

  label: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 13,
    color: '#222222',
    marginLeft: 2,
    marginBottom: 4,
  },

  input: {
    height: 45,
    borderWidth: 1,
    borderColor: '#D0D0D0',
    borderRadius: 17,
    paddingHorizontal: 12,
    fontFamily: 'Poppins_400Regular',
    fontSize: 12,
    color: '#222222',
    backgroundColor:'#F2F2F2',
    textAlignVertical: 'center',
  },

  inputDetalhes: {
    height: 78,
    borderRadius: 13,
    paddingTop: 10,
  },

  botoes: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginTop: 48,
    paddingHorizontal: 20,
  },

  botao: {
    width: 146,
    height: 43,
    backgroundColor: '#FFFFFF',
    borderRadius: 9,
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000000',
    shadowOffset: {
      width: 0,
      height: 1,
    },
    shadowOpacity: 0.15,
    shadowRadius: 2,
    elevation: 2,
  },

  textoBotao: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 13,
    color: '#111111',
  },
  textoData: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 12,
    color: '#222222',
    lineHeight: 45,
  },
textoHorario: {
  fontFamily: 'Poppins_400Regular',
  fontSize: 12,
  color: '#222222',
  lineHeight: 45,
},
});