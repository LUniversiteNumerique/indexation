node {
  stage('SCM') {
    checkout scm
  }
  stage('SonarQube Analysis') {
    agent {
      docker {
        image 'sonarsource/sonar-scanner-cli:4.6' args '-v $PWD:/usr/src'
      }
    }
    steps {
      withSonarQubeEnv('SonarQube SSL') {
        script {
          // Execution de l'analyse sonar
          sh 'sonar-scanner -Dsonar.projectKey=$SONARPROJECTKEY -Dsonar.projectVersion=$BUILDVERSION -Dproject.settings=sonar-project.properties'
        }
      }
    }
  }
}
