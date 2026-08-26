import { Link } from "expo-router";
import { Image, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View } from "react-native";

import { Button } from "@/components/button";
import { Input } from "@/components/input";


export default function Signup(){
  return(
    <KeyboardAvoidingView style={{flex: 1 }} behavior={Platform.select({ios: "padding", android:"height"})}>
    <ScrollView contentContainerStyle={{ flexGrow: 1}} keyboardShouldPersistTaps= "handled" showsVerticalScrollIndicator={false}>
      
      
      <View style={styles.container}>
        <Image source={require("@/assets/img1.png")}
        style={styles.ilustration}
        />
        <Text style={styles.titulo}>Cadastrar</Text>
        <Text style={styles.subtitulo}>Crie sua conta para acessar</Text>
        
        <View style={styles.form}>

        <Input placeholder="Nome" /> 
        <Input placeholder="E-mail" keyboardType="email-address"/> 
        <Input placeholder="Senha" secureTextEntry />   
        <Input placeholder="Confirmar a senha" secureTextEntry /> 

        <Button label="Cadastrar" />
        </View>  
        <Text style={styles.footerText}>
          Já tem uma conta? {" "}
          <Link href="/login" style={styles.footerLink}>Entre aqui.</Link>
        </Text>
      </View>
      </ScrollView>
      </KeyboardAvoidingView>
    )
}

const styles = StyleSheet.create({
  container: {
    flex: 1, 
    backgroundColor: "#FFE07A",
    padding: 32,    
  },
   ilustration: {
    width: "100%",
    height: 200,
    resizeMode: "contain",
    marginTop: 20,
  },
   titulo: {
    marginTop: 10,
    fontSize: 32,
    fontWeight: 900,
    fontFamily: "Poppins_700Bold",
   },
   subtitulo: {
    fontSize: 18,
    fontWeight: 700,
    fontFamily: "Poppins_600SemiBold",
   },
   form: {
    marginTop: -20,
    gap: 12,
   },
   footerText: {
    textAlign: "center",
    marginTop: 24,
    color: "#585860",
   },
   footerLink: {
     color: "#8397f1",
     fontWeight: 700,
   },
})
