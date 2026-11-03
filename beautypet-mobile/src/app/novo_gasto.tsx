import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';

import {
  ArrowLeft,
  X,
} from 'lucide-react-native';

import { useFeedback } from '../context/feedbackContext';

import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { api } from '../lib/api';

type Categoria = {
  id: number;
  nome: string;
};

type Pet = {
  id: number;
  nome: string;
};

function normalizarNome(valor: string) {
  return valor
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

function emojiCategoria(nome: string) {
  const categoria =
    normalizarNome(nome);

  if (
    categoria.includes('higiene')
  ) {
    return '🛁';
  }

  if (
    categoria.includes('alimenta')
  ) {
    return '🍗';
  }

  if (
    categoria.includes('saude')
  ) {
    return '💊';
  }

  if (
    categoria.includes('lazer')
  ) {
    return '🎾';
  }

  if (
    categoria.includes('beauty')
  ) {
    return '🐾';
  }

  return '📦';
}

function dataLocalHoje() {
  const hoje = new Date();

  const dia = String(
    hoje.getDate()
  ).padStart(2, '0');

  const mes = String(
    hoje.getMonth() + 1
  ).padStart(2, '0');

  return `${dia}/${mes}/${hoje.getFullYear()}`;
}

function mascararData(valor: string) {
  const numeros = valor
    .replace(/\D/g, '')
    .slice(0, 8);

  if (numeros.length <= 2) {
    return numeros;
  }

  if (numeros.length <= 4) {
    return `${numeros.slice(
      0,
      2
    )}/${numeros.slice(2)}`;
  }

  return `${numeros.slice(
    0,
    2
  )}/${numeros.slice(
    2,
    4
  )}/${numeros.slice(4)}`;
}

function dataBrParaIso(valor: string) {
  const resultado = valor.match(
    /^(\d{2})\/(\d{2})\/(\d{4})$/
  );

  if (!resultado) {
    return null;
  }

  const [, dia, mes, ano] =
    resultado;

  const data = new Date(
    Date.UTC(
      Number(ano),
      Number(mes) - 1,
      Number(dia)
    )
  );

  if (
    data.getUTCFullYear() !==
      Number(ano) ||
    data.getUTCMonth() !==
      Number(mes) - 1 ||
    data.getUTCDate() !==
      Number(dia)
  ) {
    return null;
  }

  return `${ano}-${mes}-${dia}`;
}

export default function NovoGasto() {
  const router = useRouter();

  const [categorias, setCategorias] =
    useState<Categoria[]>([]);

  const [pets, setPets] =
    useState<Pet[]>([]);

  const [categoriaId, setCategoriaId] =
    useState<number | null>(null);

  const [petId, setPetId] =
    useState<number | null>(null);

  const [descricao, setDescricao] =
    useState('');

  const [valor, setValor] =
    useState('');

  const [dataGasto, setDataGasto] =
    useState(dataLocalHoje());

  const [carregando, setCarregando] =
    useState(true);

  const [salvando, setSalvando] =
    useState(false);

  const { executar, mostrarToast } =
    useFeedback();

  useEffect(() => {
    Promise.all([
      api<{
        categorias?: Categoria[];
      }>('categorias_gasto'),

      api<{
        pets?: Pet[];
      }>('meus_pets'),
    ])
      .then(
        ([
          respostaCategorias,
          respostaPets,
        ]) => {
          setCategorias(
            respostaCategorias.categorias ||
              []
          );

          setPets(
            respostaPets.pets || []
          );
        }
      )
      .catch((erro) => {
        Alert.alert(
          'Novo gasto',

          erro instanceof Error
            ? erro.message
            : 'Não foi possível carregar os dados.'
        );
      })
      .finally(() => {
        setCarregando(false);
      });
  }, []);

  async function adicionarGasto() {
    const dataIso =
      dataBrParaIso(dataGasto);

    const categoriaSelecionada =
      categorias.find(
        (categoria) =>
          Number(categoria.id) ===
          categoriaId
      );

    if (
      !categoriaId ||
      !descricao.trim() ||
      !valor.trim() ||
      !dataIso
    ) {
      Alert.alert(
        'Campos incompletos',
        'Preencha a categoria, a descrição, o valor e uma data válida.'
      );

      return;
    }

    if (salvando) {
      return;
    }

    try {
      setSalvando(true);

      await executar(
        'Adicionando o gasto...',
        () =>
          api(
            'adicionar_gasto',
            {
              categoria_id: categoriaId,

              categoria_nome:
                categoriaSelecionada?.nome,

              pet_id: petId,

              descricao:
                descricao.trim(),

              valor,

              data_gasto: dataIso,
            }
          )
      );

      mostrarToast(
        'Pronto! 💛 Gasto adicionado com sucesso!'
      );

      router.replace('/financeiro');
    } catch (erro) {
      Alert.alert(
        'Novo gasto',

        erro instanceof Error
          ? erro.message
          : 'Não foi possível adicionar o gasto.'
      );
    } finally {
      setSalvando(false);
    }
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar
        barStyle="dark-content"
        backgroundColor="#FFF4AB"
      />

      <KeyboardAvoidingView
        style={styles.container}
        behavior={
          Platform.OS === 'ios'
            ? 'padding'
            : undefined
        }
      >
        <ScrollView
          contentContainerStyle={
            styles.scrollContent
          }
          keyboardShouldPersistTaps="handled"
          showsVerticalScrollIndicator={
            false
          }
        >
          <View style={styles.header}>
            <TouchableOpacity
              style={styles.backButton}
              onPress={() =>
                router.back()
              }
            >
              <ArrowLeft
                size={24}
                color="#222222"
              />
            </TouchableOpacity>

            <View style={styles.headerText}>
              <Text
                style={
                  styles.headerTitle
                }
              >
                Financeiro
              </Text>

              <Text
                style={
                  styles.headerSubtitle
                }
              >
                Controle os gastos dos
                seus pets
              </Text>
            </View>
          </View>

          <View style={styles.card}>
            <View
              style={styles.cardHeader}
            >
              <Text
                style={styles.cardTitle}
              >
                Novo gasto
              </Text>

              <TouchableOpacity
                style={
                  styles.closeButton
                }
                onPress={() =>
                  router.back()
                }
              >
                <X
                  size={22}
                  color="#222222"
                />
              </TouchableOpacity>
            </View>

            <Text style={styles.label}>
              Descrição
            </Text>

            <TextInput
              style={
                styles.descriptionInput
              }
              placeholder="Ex: Ração premium"
              placeholderTextColor="#999999"
              value={descricao}
              onChangeText={setDescricao}
              multiline
              textAlignVertical="top"
            />

            <Text
              style={[
                styles.label,
                styles.categoryLabel,
              ]}
            >
              Categoria
            </Text>

            {carregando ? (
              <Text
                style={styles.loadingText}
              >
                Carregando categorias...
              </Text>
            ) : (
              <View
                style={
                  styles.categoriesContainer
                }
              >
                {categorias.map(
                  (categoria) => {
                    const id = Number(
                      categoria.id
                    );

                    const selecionada =
                      categoriaId === id;

                    return (
                      <TouchableOpacity
                        key={categoria.id}
                        style={[
                          styles.categoryButton,

                          selecionada &&
                            styles.categorySelected,
                        ]}
                        activeOpacity={0.8}
                        onPress={() =>
                          setCategoriaId(id)
                        }
                      >
                        <Text
                          style={
                            styles.categoryEmoji
                          }
                        >
                          {emojiCategoria(
                            categoria.nome
                          )}
                        </Text>

                        <Text
                          numberOfLines={1}
                          style={[
                            styles.categoryText,

                            selecionada &&
                              styles.categoryTextSelected,
                          ]}
                        >
                          {categoria.nome}
                        </Text>
                      </TouchableOpacity>
                    );
                  }
                )}
              </View>
            )}

            {pets.length > 0 && (
              <>
                <Text
                  style={[
                    styles.label,
                    styles.petLabel,
                  ]}
                >
                  Pet (opcional)
                </Text>

                <View
                  style={
                    styles.petsContainer
                  }
                >
                  {pets.map((pet) => {
                    const id = Number(
                      pet.id
                    );

                    const selecionado =
                      petId === id;

                    return (
                      <TouchableOpacity
                        key={pet.id}
                        style={[
                          styles.petButton,

                          selecionado &&
                            styles.petSelected,
                        ]}
                        onPress={() =>
                          setPetId(
                            selecionado
                              ? null
                              : id
                          )
                        }
                      >
                        <Text
                          style={[
                            styles.petText,

                            selecionado &&
                              styles.petTextSelected,
                          ]}
                        >
                          {pet.nome}
                        </Text>
                      </TouchableOpacity>
                    );
                  })}
                </View>
              </>
            )}

            <View style={styles.row}>
              <View
                style={styles.inputGroup}
              >
                <Text style={styles.label}>
                  Valor (R$)
                </Text>

                <TextInput
                  style={styles.smallInput}
                  placeholder="0,00"
                  placeholderTextColor="#999999"
                  keyboardType="decimal-pad"
                  value={valor}
                  onChangeText={setValor}
                />
              </View>

              <View
                style={styles.inputGroup}
              >
                <Text style={styles.label}>
                  Data
                </Text>

                <TextInput
                  style={styles.smallInput}
                  placeholder="DD/MM/AAAA"
                  placeholderTextColor="#999999"
                  keyboardType="numeric"
                  value={dataGasto}
                  onChangeText={(texto) =>
                    setDataGasto(
                      mascararData(texto)
                    )
                  }
                  maxLength={10}
                />
              </View>
            </View>

            <TouchableOpacity
              style={[
                styles.addExpenseButton,

                salvando &&
                  styles.buttonDisabled,
              ]}
              activeOpacity={0.85}
              disabled={salvando}
              onPress={adicionarGasto}
            >
              <Text
                style={
                  styles.addButtonText
                }
              >
                {salvando
                  ? 'Adicionando...'
                  : 'Adicionar'}
              </Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#FFF4AB',
  },

  container: {
    flex: 1,
    backgroundColor: '#FFF4AB',
  },

  scrollContent: {
    paddingHorizontal: 16,
    paddingTop: 22,
    paddingBottom: 35,
  },

  header: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 14,
  },

  backButton: {
    width: 38,
    height: 40,
    justifyContent: 'center',
  },

  headerText: {
    marginLeft: 2,
  },

  headerTitle: {
    fontSize: 21,
    color: '#222222',
    fontFamily: 'Poppins_600SemiBold',
  },

  headerSubtitle: {
    fontSize: 11,
    color: '#666666',
    marginTop: 1,
    fontFamily: 'Poppins_400Regular',
  },

  card: {
    backgroundColor: '#FFFFFF',
    borderRadius: 16,
    paddingHorizontal: 15,
    paddingTop: 16,
    paddingBottom: 20,

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 3,
    },

    shadowOpacity: 0.12,
    shadowRadius: 5,
    elevation: 4,
  },

  cardHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginBottom: 18,
  },

  cardTitle: {
    fontSize: 20,
    color: '#171717',
    fontFamily: 'Poppins_600SemiBold',
  },

  closeButton: {
    width: 34,
    height: 34,
    justifyContent: 'center',
    alignItems: 'center',
  },

  label: {
    fontSize: 13,
    color: '#272727',
    marginBottom: 6,
    fontFamily: 'Poppins_500Medium',
  },

  descriptionInput: {
    height: 70,
    borderWidth: 1,
    borderColor: '#D9D9D9',
    borderRadius: 11,
    paddingHorizontal: 11,
    paddingTop: 10,
    fontSize: 12,
    color: '#222222',
    backgroundColor: '#FAFAFA',
    fontFamily: 'Poppins_400Regular',
  },

  categoryLabel: {
    marginTop: 18,
  },

  loadingText: {
    fontSize: 11,
    color: '#777777',
    fontFamily: 'Poppins_400Regular',
  },

  categoriesContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 7,
  },

  categoryButton: {
    width: '31.5%',
    height: 68,
    backgroundColor: '#FAFAFA',
    borderRadius: 10,
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderColor: '#EEEEEE',
  },

  categorySelected: {
    backgroundColor: '#FFF4AB',
    borderColor: '#E3C63D',
    borderWidth: 1.5,
  },

  categoryEmoji: {
    fontSize: 21,
    marginBottom: 3,
  },

  categoryText: {
    maxWidth: '92%',
    fontSize: 10,
    color: '#333333',
    fontFamily: 'Poppins_400Regular',
  },

  categoryTextSelected: {
    color: '#222222',
    fontFamily: 'Poppins_600SemiBold',
  },

  petLabel: {
    marginTop: 18,
  },

  petsContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 7,
  },

  petButton: {
    borderRadius: 18,
    paddingHorizontal: 13,
    paddingVertical: 8,
    backgroundColor: '#F2F2F2',
    borderWidth: 1,
    borderColor: '#E5E5E5',
  },

  petSelected: {
    backgroundColor: '#EF3038',
    borderColor: '#EF3038',
  },

  petText: {
    fontSize: 11,
    color: '#333333',
    fontFamily: 'Poppins_400Regular',
  },

  petTextSelected: {
    color: '#FFFFFF',
    fontFamily: 'Poppins_600SemiBold',
  },

  row: {
    flexDirection: 'row',
    gap: 9,
    marginTop: 22,
  },

  inputGroup: {
    flex: 1,
  },

  smallInput: {
    height: 44,
    borderWidth: 1,
    borderColor: '#D9D9D9',
    borderRadius: 10,
    paddingHorizontal: 10,
    fontSize: 12,
    color: '#222222',
    backgroundColor: '#FAFAFA',
    fontFamily: 'Poppins_400Regular',
  },

  addExpenseButton: {
    height: 48,
    backgroundColor: '#FFE16B',
    borderRadius: 11,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 26,

    shadowColor: '#000000',

    shadowOffset: {
      width: 0,
      height: 2,
    },

    shadowOpacity: 0.14,
    shadowRadius: 3,
    elevation: 3,
  },

  buttonDisabled: {
    opacity: 0.6,
  },

  addButtonText: {
    fontSize: 14,
    color: '#171717',
    fontFamily: 'Poppins_600SemiBold',
  },
});