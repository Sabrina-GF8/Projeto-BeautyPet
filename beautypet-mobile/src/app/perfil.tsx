import {
  Alert,
  ScrollView,
  StyleSheet,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';

import { useEffect, useState } from 'react';

import {
  Poppins_400Regular,
  Poppins_600SemiBold,
  useFonts,
} from '@expo-google-fonts/poppins';

import { Ionicons } from '@expo/vector-icons';

import { useRouter } from 'expo-router';
import { useFeedback } from '../context/feedbackContext';
import { api, limparSessao } from '../lib/api';

import {
  CalendarDays,
  Home as HomeIcon,
  UserRound,
  Wallet,
} from 'lucide-react-native';


export default function Perfil() {

  const router = useRouter();
  const { executar } = useFeedback();

  const [menuAberto, setMenuAberto] = useState(false);
  const [dadosPessoaisAberto, setDadosPessoaisAberto] = useState(false);
  const [segurancaAberta, setSegurancaAberta] = useState(false);
  const [cliente, setCliente] = useState<{
    nome?: string;
    email?: string | null;
    telefone_principal?: string | null;
    cidade?: string | null;
  }>({});
  const [pets, setPets] = useState<{ id: number; nome: string }[]>([]);

  function carregarPerfil() {
    api<{
      cliente: {
        nome?: string;
        email?: string | null;
        telefone_principal?: string | null;
        cidade?: string | null;
      };
      pets: { id: number; nome: string }[];
    }>('perfil')
      .then((resposta) => {
        setCliente(resposta.cliente || {});
        setPets(resposta.pets || []);
      })
      .catch((erro) => {
        // HEURÍSTICA 9: antes o erro era engolido e o perfil aparecia em branco.
        Alert.alert(
          'Não foi possível carregar seu perfil',
          erro instanceof Error ? erro.message : 'Verifique sua conexão e tente novamente.',
          [
            { text: 'Agora não', style: 'cancel' },
            { text: 'Tentar novamente', onPress: carregarPerfil },
          ]
        );
      });
  }

  useEffect(() => {
    carregarPerfil();
  }, []);

  async function sair() {
    try {
      await executar('Saindo da conta...', () => api('logout'));
    } catch {
      // Limpa a sessão local mesmo se o servidor estiver indisponível.
    }

    limparSessao();
    router.replace('/login');
  }

  const [fontsLoaded] = useFonts({
    Poppins_400Regular,
    Poppins_600SemiBold,
  });


  if (!fontsLoaded) {
    return null;
  }


  return (
    <View style={styles.container}>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={styles.conteudo}
        showsVerticalScrollIndicator={false}
      >

        {/* CABEÇALHO */}

        <View style={styles.cabecalho}>

          <Text style={styles.titulo}>
            Perfil
          </Text>


          <TouchableOpacity
            style={styles.botaoConfiguracoes}
            activeOpacity={0.7}
            onPress={() => setMenuAberto(true)}
          >

            <Ionicons
              name="settings-outline"
              size={25}
              color="#333333"
            />

          </TouchableOpacity>

        </View>


        {/* FOTO */}

        <View style={styles.fotoContainer}>

          <View style={styles.foto} />

          <TouchableOpacity
            style={styles.botaoCamera}
            activeOpacity={0.7}
          >

            <Ionicons
              name="camera-outline"
              size={22}
              color="#FFFFFF"
            />

          </TouchableOpacity>

        </View>


        {/* NOME */}

        <Text style={styles.nome}>
          {cliente.nome || '—'}
        </Text>


        {/* CARD DE INFORMAÇÕES */}

        <View style={styles.card}>

          <View style={styles.cabecalhoCard}>

            <Text style={styles.tituloCard}>
              INFORMAÇÕES
            </Text>

          </View>


          {/* E-MAIL */}

          <View style={styles.linha}>

            <View style={styles.iconeContainer}>

              <Ionicons
                name="mail-outline"
                size={22}
                color="#333333"
              />

            </View>

            <View>

              <Text style={styles.label}>
                E-mail
              </Text>

              <Text style={styles.valorSublinhado}>
                {cliente.email || '—'}
              </Text>

            </View>

          </View>


          {/* TELEFONE */}

          <View style={styles.linha}>

            <View style={styles.iconeContainer}>

              <Ionicons
                name="call-outline"
                size={22}
                color="#333333"
              />

            </View>

            <View>

              <Text style={styles.label}>
                Telefone
              </Text>

              <Text style={styles.valor}>
                {cliente.telefone_principal || '—'}
              </Text>

            </View>

          </View>


          {/* CIDADE */}

          <View style={styles.linhaSemBorda}>

            <View style={styles.iconeContainer}>

              <Ionicons
                name="location-outline"
                size={22}
                color="#333333"
              />

            </View>

            <View>

              <Text style={styles.label}>
                Cidade
              </Text>

              <Text style={styles.valor}>
                {cliente.cidade || '—'}
              </Text>

            </View>

          </View>

        </View>


        {/* CARD MEUS PETS */}

        <View style={styles.cardPets}>

          <View style={styles.cabecalhoPets}>

            <Text style={styles.tituloCard}>
              MEUS PETS
            </Text>

            <TouchableOpacity onPress={() => router.push('/novo_pet')}>

              <Text style={styles.adicionar}>
                + Adicionar
              </Text>

            </TouchableOpacity>

          </View>


          {/* ESPAÇO DOS PETS */}

          {pets.length > 0 ? (
            pets.map((pet) => (
              <View key={pet.id} style={styles.semPet}>
                <Text style={styles.textoSemPet}>{pet.nome}</Text>
              </View>
            ))
          ) : (
            <View style={styles.semPet}>
              <Text style={styles.textoSemPet}>Nenhum pet cadastrado</Text>
            </View>
          )}


          {/* ADICIONAR NOVO PET */}

          <TouchableOpacity
            style={styles.novoPet}
            activeOpacity={0.7}
            onPress={() => router.push('/novo_pet')}
          >

            <View style={styles.iconeNovoPet}>

              <Ionicons
                name="paw-outline"
                size={20}
                color="#FF5A5A"
              />

            </View>

            <View>

              <Text style={styles.textoNovoPet}>
                Adicionar novo pet
              </Text>

              <Text style={styles.subtextoNovoPet}>
                Cadastre mais pets da família
              </Text>

            </View>

          </TouchableOpacity>

        </View>

      </ScrollView>


      {/* MENU LATERAL DE CONFIGURAÇÕES */}

      {menuAberto && (

        <View style={styles.menuOverlay}>

          {/* ÁREA ESCURA PARA FECHAR */}

          <TouchableOpacity
            style={styles.areaFechar}
            activeOpacity={1}
            onPress={() => setMenuAberto(false)}
          />


          {/* MENU */}

          <View style={styles.menuLateral}>

            {/* CABEÇALHO DO MENU */}

            <View style={styles.menuCabecalho}>

              <Text style={styles.menuTitulo}>
                Configurações
              </Text>

              <TouchableOpacity
                onPress={() => setMenuAberto(false)}
                activeOpacity={0.7}
              >

                <Ionicons
                  name="close-outline"
                  size={28}
                  color="#333333"
                />

              </TouchableOpacity>

            </View>


            <View style={styles.separador} />


            {/* DADOS PESSOAIS */}

            <TouchableOpacity
              style={styles.opcao}
              activeOpacity={0.7}
              onPress={() =>
                setDadosPessoaisAberto(!dadosPessoaisAberto)
              }
            >

              <View style={styles.iconeOpcao}>

                <Ionicons
                  name="person-outline"
                  size={20}
                  color="#333333"
                />

              </View>

              <Text style={styles.textoOpcao}>
                Dados pessoais
              </Text>

              <Ionicons
                name={
                  dadosPessoaisAberto
                    ? 'chevron-up-outline'
                    : 'chevron-down-outline'
                }
                size={20}
                color="#999999"
              />

            </TouchableOpacity>


            {/* SUBOPÇÕES DOS DADOS PESSOAIS */}

            {dadosPessoaisAberto && (

              <View style={styles.subOpcoes}>

                <TouchableOpacity
                  style={styles.subOpcao}
                  activeOpacity={0.7}
                >

                  <Text style={styles.textoSubOpcao}>
                    Alterar e-mail
                  </Text>

                  <Ionicons
                    name="chevron-forward-outline"
                    size={18}
                    color="#999999"
                  />

                </TouchableOpacity>


                <TouchableOpacity
                  style={styles.subOpcao}
                  activeOpacity={0.7}
                >

                  <Text style={styles.textoSubOpcao}>
                    Alterar telefone
                  </Text>

                  <Ionicons
                    name="chevron-forward-outline"
                    size={18}
                    color="#999999"
                  />

                </TouchableOpacity>


                <TouchableOpacity
                  style={styles.subOpcao}
                  activeOpacity={0.7}
                >

                  <Text style={styles.textoSubOpcao}>
                    Alterar cidade
                  </Text>

                  <Ionicons
                    name="chevron-forward-outline"
                    size={18}
                    color="#999999"
                  />

                </TouchableOpacity>

              </View>

            )}


            {/* SEGURANÇA E PRIVACIDADE */}

            <TouchableOpacity
              style={styles.opcao}
              activeOpacity={0.7}
              onPress={() =>
                setSegurancaAberta(!segurancaAberta)
              }
            >

              <View style={styles.iconeOpcao}>

                <Ionicons
                  name="shield-checkmark-outline"
                  size={20}
                  color="#333333"
                />

              </View>

              <Text style={styles.textoOpcao}>
                Segurança e privacidade
              </Text>

              <Ionicons
                name={
                  segurancaAberta
                    ? 'chevron-up-outline'
                    : 'chevron-down-outline'
                }
                size={20}
                color="#999999"
              />

            </TouchableOpacity>


            {/* ALTERAR SENHA */}

            {segurancaAberta && (

              <View style={styles.subOpcoes}>

                <TouchableOpacity
                  style={styles.subOpcao}
                  activeOpacity={0.7}
                >

                  <Text style={styles.textoSubOpcao}>
                    Alterar senha
                  </Text>

                  <Ionicons
                    name="chevron-forward-outline"
                    size={18}
                    color="#999999"
                  />

                </TouchableOpacity>

              </View>

            )}


            {/* LOGOUT */}

            <View style={styles.linhaLogout} />

            <TouchableOpacity
              style={styles.opcaoLogout}
              activeOpacity={0.7}
              onPress={sair}
            >

              <View style={styles.iconeLogout}>

                <Ionicons
                  name="log-out-outline"
                  size={21}
                  color="#E53935"
                />

              </View>

              <Text style={styles.textoLogout}>
                Sair da conta
              </Text>

            </TouchableOpacity>

          </View>

        </View>

      )}


      {/* MENU INFERIOR */}

      <View style={styles.bottomMenu}>

        {/* HOME */}

        <TouchableOpacity
          style={styles.menuItem}
          onPress={() => router.push('/home')}
        >

          <HomeIcon
            size={25}
            color="#5F6269"
          />

          <Text style={styles.menuText}>
            Home
          </Text>

        </TouchableOpacity>


        {/* AGENDA */}

        <TouchableOpacity
          style={styles.menuItem}
          onPress={() => router.push('/agenda')}
        >

          <CalendarDays
            size={25}
            color="#5F6269"
          />

          <Text style={styles.menuText}>
            Agenda
          </Text>

        </TouchableOpacity>


        {/* FINANCEIRO */}

        <TouchableOpacity
          style={styles.menuItem}
          onPress={() => router.push('/financeiro')}
        >

          <Wallet
            size={25}
            color="#5F6269"
          />

          <Text style={styles.menuText}>
            Financeiro
          </Text>

        </TouchableOpacity>


        {/* PERFIL */}

        <TouchableOpacity
          style={styles.menuItem}
          onPress={() => router.push('/perfil')}
        >

          <UserRound
            size={25}
            color="#EF3038"
          />

          <Text style={styles.menuTextActive}>
            Perfil
          </Text>

          <View style={styles.activeLine} />

        </TouchableOpacity>

      </View>

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
    paddingTop: 55,
    paddingHorizontal: 20,
    paddingBottom: 100,
  },


  /* TÍTULO */

  titulo: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 20,
    color: '#222222',
  },


  /* FOTO */

  fotoContainer: {
    alignSelf: 'center',
    marginTop: -10,
    position: 'relative',
  },


  foto: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: '#F2F2F2',
    borderWidth: 3,
    borderColor: '#F29BC2',
  },


  botaoCamera: {
    position: 'absolute',
    right: -2,
    bottom: -2,
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#FF1E25',
    justifyContent: 'center',
    alignItems: 'center',
  },


  nome: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 18,
    color: '#999999',
    textAlign: 'center',
    marginTop: 8,
    marginBottom: 5,
  },


  /* CARD DE INFORMAÇÕES */

  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 13,
    overflow: 'hidden',
    shadowColor: '#000000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 4,
  },


  cabecalhoCard: {
    height: 40,
    paddingHorizontal: 13,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: '#FFD84D',
  },


  tituloCard: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 14,
    color: '#333333',
  },


  linha: {
    minHeight: 70,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: '#FFD84D',
  },


  linhaSemBorda: {
    minHeight: 70,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
  },


  iconeContainer: {
    width: 38,
    height: 38,
    borderRadius: 22,
    backgroundColor: '#FFD84D',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 10,
  },


  icone: {
    fontSize: 17,
    color: '#333333',
  },


  label: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 14,
    color: '#999999',
  },


  valor: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 11,
    color: '#999999',
  },


  valorSublinhado: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 11,
    color: '#999999',
    textDecorationLine: 'underline',
  },


  /* CARD MEUS PETS */

  cardPets: {
    marginTop: 19,
    backgroundColor: '#FFFFFF',
    borderRadius: 13,
    overflow: 'hidden',
    shadowColor: '#000000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.15,
    shadowRadius: 4,
    elevation: 4,
  },


  cabecalhoPets: {
    height: 40,
    paddingHorizontal: 13,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderBottomWidth: 1,
    borderBottomColor: '#FFD84D',
  },


  adicionar: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 14,
    color: '#FF2020',
  },


  semPet: {
    height: 60,
    justifyContent: 'center',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: '#FFD84D',
  },


  textoSemPet: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 14,
    color: '#999999',
  },


  novoPet: {
    height: 70,
    paddingHorizontal: 14,
    flexDirection: 'row',
    alignItems: 'center',
  },


  iconeNovoPet: {
    width: 36,
    height: 36,
    borderRadius: 18,
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: '#FF5A5A',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 9,
  },


  textoNovoPet: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 14,
    color: '#FF4A4A',
  },


  subtextoNovoPet: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 12,
    color: '#999999',
    marginTop: 2,
  },


  /* BOTÃO DE CONFIGURAÇÕES */

  cabecalho: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },


  botaoConfiguracoes: {
    width: 50,
    height: 50,
    borderRadius: 26,
    backgroundColor: '#FFFFFF',
    justifyContent: 'center',
    alignItems: 'center',
    shadowColor: '#000000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.15,
    shadowRadius: 3,
    elevation: 4,
  },


  /* MENU LATERAL */

  menuOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    backgroundColor: 'rgba(0, 0, 0, 0.25)',
    zIndex: 100,
  },


  areaFechar: {
    flex: 1,
  },


  menuLateral: {
    width: '78%',
    height: '100%',
    backgroundColor: '#FFFFFF',
    paddingTop: 55,
    paddingHorizontal: 20,
    shadowColor: '#000000',
    shadowOffset: {
      width: -3,
      height: 0,
    },
    shadowOpacity: 0.2,
    shadowRadius: 6,
    elevation: 10,
  },


  menuCabecalho: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
  },


  menuTitulo: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 19,
    color: '#222222',
  },


  separador: {
    height: 1,
    backgroundColor: '#FFD84D',
    marginTop: 15,
    marginBottom: 25,
  },


  /* OPÇÕES PRINCIPAIS */

  opcao: {
    minHeight: 55,
    flexDirection: 'row',
    alignItems: 'center',
    borderBottomWidth: 1,
    borderBottomColor: '#EEEEEE',
  },


  iconeOpcao: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: '#FFF59D',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },


  textoOpcao: {
    flex: 1,
    fontFamily: 'Poppins_400Regular',
    fontSize: 13,
    color: '#333333',
  },


  /* SUBOPÇÕES */

  subOpcoes: {
    backgroundColor: '#FAFAFA',
    paddingLeft: 50,
    paddingBottom: 5,
  },


  subOpcao: {
    minHeight: 42,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingRight: 5,
    borderBottomWidth: 1,
    borderBottomColor: '#EEEEEE',
  },


  textoSubOpcao: {
    fontFamily: 'Poppins_400Regular',
    fontSize: 12,
    color: '#666666',
  },


  /* LOGOUT */

  linhaLogout: {
    height: 1,
    backgroundColor: '#EEEEEE',
    marginTop: 30,
    marginBottom: 8,
  },


  opcaoLogout: {
    height: 55,
    flexDirection: 'row',
    alignItems: 'center',
  },


  iconeLogout: {
    width: 38,
    height: 38,
    borderRadius: 19,
    backgroundColor: '#FFE5E5',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },


  textoLogout: {
    fontFamily: 'Poppins_600SemiBold',
    fontSize: 13,
    color: '#E53935',
  },


  /* MENU INFERIOR */

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
